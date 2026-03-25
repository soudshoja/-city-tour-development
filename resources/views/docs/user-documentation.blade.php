<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Documentation - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        .doc-gif { border-radius: 0.5rem; border: 2px solid #e5e7eb; box-shadow: 0 2px 8px rgba(0,0,0,0.12); width: 100%; max-width: 100%; height: auto; }
        .dark .doc-gif { border-color: #374151; }
        @media (max-width: 639px) {
            .info-box, .warn-box { padding: 0.75rem 1rem; }
            section h2 { font-size: 1.25rem; }
            section h3 { font-size: 1rem; }
            .doc-gif-wrap .gif-badge { top: 8px; right: 8px; font-size: 10px; padding: 2px 8px; }
        }
        /* RTL Support */
        [dir="rtl"] body { font-family: 'Noto Sans Arabic', 'Inter', sans-serif; }
        [dir="rtl"] .info-box { border-left: none; border-right: 4px solid #2945a2; border-radius: 0.5rem 0 0 0.5rem; }
        [dir="rtl"] .dark .info-box { border-left-color: transparent; border-right-color: #536bd3; }
        [dir="rtl"] .warn-box { border-left: none; border-right: 4px solid #f59e0b; border-radius: 0.5rem 0 0 0.5rem; }
        [dir="rtl"] .dark .warn-box { border-left-color: transparent; border-right-color: #fbbf24; }
        [dir="rtl"] .sidebar-link:hover { transform: translateX(-4px); }
        [dir="rtl"] .doc-gif-wrap .gif-badge { right: auto; left: 12px; }
        @media (max-width: 639px) {
            [dir="rtl"] .doc-gif-wrap .gif-badge { right: auto; left: 8px; }
        }
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

    <div class="fixed top-0 start-0 w-full h-1 z-50 bg-gray-200 dark:bg-gray-700">
        <div id="progress-bar" class="h-full bg-primary-500" :style="'width:' + scrollProgress + '%'"></div>
    </div>

    <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-1 z-40">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-3 sm:py-4 flex justify-between items-center gap-2">
            <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 flex-shrink-0">
                    <i class="fas fa-bars text-gray-600 dark:text-gray-300"></i>
                </button>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 sm:h-8 sm:w-8 text-primary-500 flex-shrink-0 hidden sm:block" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/>
                </svg>
                <h1 class="text-base sm:text-xl font-bold text-gray-900 dark:text-white truncate">{{ __('doc.header.title') }}</h1>
                <span class="text-xs bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 px-2 py-0.5 sm:py-1 rounded-full font-semibold uppercase flex-shrink-0">{{ $roleName }}</span>
            </div>
            <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 whitespace-nowrap">
                    <i class="fas fa-arrow-left me-1"></i> {{ __('doc.header.backToApp') }}
                </a>
                <a href="{{ route('locale.switch', ['lang' => app()->getLocale() === 'en' ? 'ar' : 'en']) }}?redirect={{ urlencode(request()->url()) }}"
                   class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                   title="{{ app()->getLocale() === 'en' ? 'Switch to Arabic' : 'Switch to English' }}">
                    <span class="text-sm font-bold text-gray-600 dark:text-gray-300">
                        {{ app()->getLocale() === 'en' ? 'AR' : 'EN' }}
                    </span>
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

    <div x-show="sidebarOpen"
         class="fixed inset-y-0 z-50 w-72 bg-white dark:bg-gray-800 shadow-xl lg:hidden overflow-y-auto transition-transform duration-200 {{ app()->getLocale() === 'ar' ? 'right-0' : 'left-0' }}"
         x-transition:enter="transition ease-in-out duration-200 transform"
         x-transition:enter-start="{{ app()->getLocale() === 'ar' ? 'translate-x-full' : '-translate-x-full' }}"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in-out duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="{{ app()->getLocale() === 'ar' ? 'translate-x-full' : '-translate-x-full' }}"
        <div class="p-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('doc.header.navigation') }}</h2>
                <button @click="sidebarOpen = false" class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-times text-gray-500"></i>
                </button>
            </div>
            <div class="relative mb-4">
                <input type="text" x-model="searchQuery" @input="filterSections()" placeholder="{{ __('doc.header.searchPlaceholder') }}"
                       class="w-full ps-9 pe-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <i class="fas fa-search absolute start-3 top-2.5 text-gray-400"></i>
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
                            <i :class="link.icon + ' w-4 me-3'"></i>
                            <span x-text="link.title"></span>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-4 sm:py-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-8">

            <div class="hidden lg:block lg:col-span-3">
                <nav class="sticky top-24 space-y-1 p-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm max-h-[calc(100vh-8rem)] overflow-y-auto">
                    <div class="relative mb-4">
                        <input type="text" x-model="searchQuery" @input="filterSections()" placeholder="{{ __('doc.header.searchPlaceholder') }}"
                               class="w-full ps-9 pe-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                        <i class="fas fa-search absolute start-3 top-2.5 text-gray-400"></i>
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
                                        <i :class="link.icon + ' w-4 me-3'" :style="activeSection === link.id ? 'color: #2945a2' : ''"></i>
                                        <span x-text="link.title"></span>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </nav>
            </div>

            <div class="mt-4 sm:mt-8 lg:mt-0 lg:col-span-9 min-w-0">

                <div id="welcome" class="bg-gradient-to-r from-primary-600 to-primary-500 rounded-xl shadow-lg p-5 sm:p-8 mb-8 sm:mb-12 text-white">
                    <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold mb-3">
                        @if($isAdmin) {{ __('doc.welcome.adminTitle') }} @elseif($isCompany) {{ __('doc.welcome.companyTitle') }} @elseif($isAgent) {{ __('doc.welcome.agentTitle') }} @elseif($isAccountant) {{ __('doc.welcome.accountantTitle') }} @elseif($isBranch) {{ __('doc.welcome.branchTitle') }} @else {{ __('doc.welcome.defaultTitle') }} @endif
                    </h1>
                    <p class="text-sm opacity-75 font-medium uppercase tracking-wide mb-1">{{ __('doc.welcome.subtitle') }} &mdash; {{ ucfirst($roleName) }}</p>
                    <p class="text-lg opacity-90 max-w-3xl">
                        @if($isAdmin) {{ __('doc.welcome.adminDesc') }}
                        @elseif($isCompany) {{ __('doc.welcome.companyDesc') }}
                        @elseif($isAgent) {{ __('doc.welcome.agentDesc') }}
                        @else {{ __('doc.welcome.defaultDesc') }}
                        @endif
                    </p>
                </div>

                <section id="getting-started" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-rocket text-primary-500 me-2"></i> {{ __('doc.gs.title') }}
                    </h2>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.gs.loggingIn') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.gs.loggingInDesc') !!}</p>
                    <div class="info-box mb-4">
                        <p class="text-sm">{!! __('doc.gs.noRegistration') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/login-flow.gif') }}" alt="Login Flow" class="doc-gif"></div>

                    @if($isAdmin)
                    <h3 class="text-lg font-semibold mb-3 mt-6">{{ __('doc.gs.admin.dashboardTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.gs.admin.dashboardDesc') !!}</p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.gs.admin.sidebarDesc') !!}</p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/admin-dashboard.gif') }}" alt="Admin Dashboard" class="doc-gif"></div>

                    @elseif($isCompany)
                    <h3 class="text-lg font-semibold mb-3 mt-6">{{ __('doc.gs.company.dashboardTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.gs.company.dashboardDesc') !!}</p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.gs.company.sidebarDesc') !!}</p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/company-dashboard.gif') }}" alt="Company Dashboard" class="doc-gif"></div>

                    @elseif($isAgent)
                    <h3 class="text-lg font-semibold mb-3 mt-6">{{ __('doc.gs.agent.afterLoginTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.gs.agent.afterLoginDesc') !!}</p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.gs.agent.sidebarDesc') !!}</p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/agent-homepage.gif') }}" alt="Agent Tasks Home" class="doc-gif"></div>

                    @else
                    <h3 class="text-lg font-semibold mb-3 mt-6">{{ __('doc.gs.default.afterLoginTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.gs.default.afterLoginDesc') !!}</p>
                    @endif

                    <h3 class="text-lg font-semibold mb-3 mt-6">{{ __('doc.gs.navigating') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.gs.navigatingDesc') !!}</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li>{!! __('doc.gs.nav1') !!}</li>
                        <li>{!! __('doc.gs.nav2') !!}</li>
                        <li>{!! __('doc.gs.nav3') !!}</li>
                        <li>{!! __('doc.gs.nav4') !!}</li>
                    </ul>
                </section>

                @can('viewAny', App\Models\Role::class)
                <section id="role-overview" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-shield-halved text-primary-500 me-2"></i> {{ __('doc.roles.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">{!! __('doc.roles.desc') !!}</p>

                    <div class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-5 @if($isAdmin) ring-2 ring-amber-400 @endif">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-amber-200 dark:bg-amber-800 text-amber-800 dark:text-amber-200 text-xs font-bold px-2 py-1 rounded">{{ __('doc.roles.admin.label') }}</span>
                            <span class="font-semibold">{{ __('doc.roles.admin.name') }}</span>
                            @if($isAdmin) <span class="bg-amber-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ __('doc.roles.admin.yourRole') }}</span> @endif
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.admin.perm1') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.admin.perm2') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.admin.perm3') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.admin.perm4') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.admin.perm5') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.admin.perm6') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.admin.perm7') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.admin.perm8') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.admin.perm9') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.admin.perm10') }}</div>
                        </div>
                    </div>

                    <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-5 @if($isCompany) ring-2 ring-blue-400 @endif">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-blue-200 dark:bg-blue-800 text-blue-800 dark:text-blue-200 text-xs font-bold px-2 py-1 rounded">{{ __('doc.roles.company.label') }}</span>
                            <span class="font-semibold">{{ __('doc.roles.company.name') }}</span>
                            @if($isCompany) <span class="bg-blue-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ __('doc.roles.admin.yourRole') }}</span> @endif
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.company.perm1') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.company.perm2') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.company.perm3') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.company.perm4') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.company.perm5') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.company.perm6') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.company.perm7') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.company.perm8') }}</div>
                            <div><i class="fas fa-times text-red-400 me-1"></i> {{ __('doc.roles.company.no1') }}</div>
                            <div><i class="fas fa-times text-red-400 me-1"></i> {{ __('doc.roles.company.no2') }}</div>
                            <div><i class="fas fa-times text-red-400 me-1"></i> {{ __('doc.roles.company.no3') }}</div>
                        </div>
                    </div>

                    <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-5 @if($isBranch) ring-2 ring-green-400 @endif">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-green-200 dark:bg-green-800 text-green-800 dark:text-green-200 text-xs font-bold px-2 py-1 rounded">{{ __('doc.roles.branch.label') }}</span>
                            <span class="font-semibold">{{ __('doc.roles.branch.name') }}</span>
                            @if($isBranch) <span class="bg-green-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ __('doc.roles.admin.yourRole') }}</span> @endif
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.branch.perm1') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.branch.perm2') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.branch.perm3') }}</div>
                            <div><i class="fas fa-times text-red-400 me-1"></i> {{ __('doc.roles.branch.no1') }}</div>
                            <div><i class="fas fa-times text-red-400 me-1"></i> {{ __('doc.roles.branch.no2') }}</div>
                        </div>
                    </div>

                    <div class="mb-6 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-5 @if($isAgent) ring-2 ring-indigo-400 @endif">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-indigo-200 dark:bg-indigo-800 text-indigo-800 dark:text-indigo-200 text-xs font-bold px-2 py-1 rounded">{{ __('doc.roles.agent.label') }}</span>
                            <span class="font-semibold">{{ __('doc.roles.agent.name') }}</span>
                            @if($isAgent) <span class="bg-indigo-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ __('doc.roles.admin.yourRole') }}</span> @endif
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.agent.perm1') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.agent.perm2') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.agent.perm3') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.agent.perm4') }}</div>
                            <div><i class="fas fa-times text-red-400 me-1"></i> {{ __('doc.roles.agent.no1') }}</div>
                            <div><i class="fas fa-times text-red-400 me-1"></i> {{ __('doc.roles.agent.no2') }}</div>
                        </div>
                    </div>

                    <div class="mb-6 bg-pink-50 dark:bg-pink-900/20 border border-pink-200 dark:border-pink-800 rounded-lg p-5 @if($isAccountant) ring-2 ring-pink-400 @endif">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-pink-200 dark:bg-pink-800 text-pink-800 dark:text-pink-200 text-xs font-bold px-2 py-1 rounded">{{ __('doc.roles.accountant.label') }}</span>
                            <span class="font-semibold">{{ __('doc.roles.accountant.name') }}</span>
                            @if($isAccountant) <span class="bg-pink-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ __('doc.roles.admin.yourRole') }}</span> @endif
                        </div>
                        <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400">
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.accountant.perm1') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.accountant.perm2') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.accountant.perm3') }}</div>
                            <div><i class="fas fa-check text-green-500 me-1"></i> {{ __('doc.roles.accountant.perm4') }}</div>
                            <div><i class="fas fa-times text-red-400 me-1"></i> {{ __('doc.roles.accountant.no1') }}</div>
                        </div>
                    </div>

                    <div class="mb-6 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="bg-purple-200 dark:bg-purple-800 text-purple-800 dark:text-purple-200 text-xs font-bold px-2 py-1 rounded">{{ __('doc.roles.client.label') }}</span>
                            <span class="font-semibold">{{ __('doc.roles.client.name') }}</span>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            <p>{!! __('doc.roles.client.desc') !!}</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.roles.managingPerms') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.roles.managingPermsDesc') !!}</p>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.roles.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.roles.step2') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.roles.step3') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">4</span>
                        <p class="text-sm">{!! __('doc.roles.step4') !!}</p>
                    </div>
                    <div class="warn-box mb-4">
                        <p class="text-sm">{!! __('doc.roles.warning') !!}</p>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.roles.permTypes') !!}</p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/admin-roles.gif') }}" alt="Roles & Permissions" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\User')
                <section id="user-management" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-users text-primary-500 me-2"></i> {{ __('doc.um.title') }}
                    </h2>

                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.um.desc') !!}</p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/user-management.gif') }}" alt="User Management Overview" class="doc-gif"></div>

                    @can('viewAny', 'App\Models\Company')
                    <div class="info-box mb-6">
                        <p class="text-sm">{!! __('doc.um.setupOrder') !!}</p>
                    </div>

                    <div id="companies" class="mb-10 scroll-mt-24">
                        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-building text-blue-500 me-2"></i> {{ __('doc.um.companies.title') }}</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.um.companies.desc') !!}</p>
                        <div class="flex items-start gap-3 mb-2">
                            <span class="step-number">1</span>
                            <p class="text-sm">{!! __('doc.um.companies.step1') !!}</p>
                        </div>
                        <div class="flex items-start gap-3 mb-4">
                            <span class="step-number">2</span>
                            <p class="text-sm">{!! __('doc.um.companies.step2') !!}</p>
                        </div>
                        <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/create-company.gif') }}" alt="Create Company" class="doc-gif"></div>
                    </div>
                    @endcan

                    @can('viewAny', App\Models\Branch::class)
                    <div id="branches" class="mb-10 scroll-mt-24">
                        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-code-branch text-green-500 me-2"></i> {{ __('doc.um.branches.title') }}</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.um.branches.desc') !!}</p>
                        <div class="flex items-start gap-3 mb-2">
                            <span class="step-number">1</span>
                            <p class="text-sm">{!! __('doc.um.branches.step1') !!}</p>
                        </div>
                        <div class="flex items-start gap-3 mb-4">
                            <span class="step-number">2</span>
                            <p class="text-sm">{!! __('doc.um.branches.step2') !!}</p>
                        </div>
                        <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/create-branch.gif') }}" alt="Create Branch" class="doc-gif"></div>
                    </div>
                    @endcan

                    @can('viewAny', App\Models\Agent::class)
                    <div id="agents" class="mb-10 scroll-mt-24">
                        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-user-tie text-indigo-500 me-2"></i> {{ __('doc.um.agents.title') }}</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.um.agents.desc') !!}</p>
                        <div class="flex items-start gap-3 mb-2">
                            <span class="step-number">1</span>
                            <div class="text-sm">{!! __('doc.um.agents.step1') !!}</div>
                        </div>
                        <div class="flex items-start gap-3 mb-4">
                            <span class="step-number">2</span>
                            <p class="text-sm">{!! __('doc.um.agents.step2') !!}</p>
                        </div>
                        <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/create-agent.gif') }}" alt="Create Agent" class="doc-gif"></div>
                    </div>
                    @endcan

                    @can('viewAny', App\Models\Client::class)
                    <div id="add-clients" class="mb-10 scroll-mt-24">
                        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-user text-purple-500 me-2"></i> {{ __('doc.um.clients.title') }}</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.um.clients.desc') !!}</p>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.um.clients.nav') !!}</p>
                        <div class="flex items-start gap-3 mb-2">
                            <span class="step-number">1</span>
                            <p class="text-sm">{!! __('doc.um.clients.step1') !!}</p>
                        </div>
                        <div class="flex items-start gap-3 mb-2">
                            <span class="step-number">2</span>
                            <p class="text-sm">{!! __('doc.um.clients.step2') !!}</p>
                        </div>
                        <div class="flex items-start gap-3 mb-4">
                            <span class="step-number">3</span>
                            <p class="text-sm">{!! __('doc.um.clients.step3') !!}</p>
                        </div>
                        <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/manage-clients.gif') }}" alt="Manage Clients" class="doc-gif"></div>
                        <div class="info-box">
                            <p class="text-sm">{!! __('doc.um.clients.editInfo') !!}</p>
                        </div>
                    </div>
                    @endcan
                </section>
                @endcan

                @can('viewAny', App\Models\Client::class)
                @cannot('viewAny', 'App\Models\User')
                <section id="my-clients" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-user text-primary-500 me-2"></i> {{ __('doc.mc.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.mc.desc') !!}</p>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.mc.whatCanDo') }}</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.mc.perm1') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.mc.perm2') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.mc.perm3') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.mc.perm4') !!}</div>
                        <div><i class="fas fa-times text-red-400 me-1"></i> {{ __('doc.mc.no1') }}</div>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.mc.addingTitle') }}</h3>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.mc.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.mc.step2') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.mc.step3') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/agent-clients.gif') }}" alt="Agent Client Management" class="doc-gif"></div>

                    <div class="info-box">
                        <p class="text-sm">{!! __('doc.mc.ownClientsOnly') !!}</p>
                    </div>
                </section>
                @endcannot
                @endcan

                @can('viewAny', App\Models\Supplier::class)
                <section id="suppliers" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-handshake text-primary-500 me-2"></i> {{ __('doc.sup.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.sup.desc') !!}</p>

                    @if($isAdmin)
                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.sup.admin.whatCanDo') }}</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-6">
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.sup.admin.perm1') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.sup.admin.perm2') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.sup.admin.perm3') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.sup.admin.perm4') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.sup.admin.perm5') !!}</div>
                    </div>
                    @elseif($isCompany)
                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.sup.company.whatCanDo') }}</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.sup.company.perm1') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.sup.company.perm2') !!}</div>
                        <div><i class="fas fa-times text-red-400 me-1"></i> {!! __('doc.sup.company.no1') !!}</div>
                        <div><i class="fas fa-times text-red-400 me-1"></i> {!! __('doc.sup.company.no2') !!}</div>
                    </div>
                    <div class="info-box mb-6">
                        <p class="text-sm">{!! __('doc.sup.company.contactAdmin') !!}</p>
                    </div>
                    @endif

                    @if($isAdmin)
                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.sup.addingTitle') }}</h3>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.sup.adding.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <div class="text-sm">
                            <p>{!! __('doc.sup.adding.step2') !!}</p>
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach(['Hotel','Flight','Visa','Insurance','Tour','Cruise','Car','Rail','eSIM','Event','Lounge','Ferry'] as $type)
                                <span class="bg-gray-100 dark:bg-gray-700 text-xs px-2 py-0.5 rounded">{{ $type }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.sup.adding.step3') !!}</p>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.sup.activatingTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.sup.activatingDesc') !!}</p>
                    @endif

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.sup.editingTitle') }}</h3>
                    @if($isAdmin)
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.sup.admin.editDesc') !!}</p>
                    @else
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.sup.company.editDesc') !!}</p>
                    @endif

                    @if($isAdmin)
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/admin-suppliers.gif') }}" alt="Supplier Management (Admin)" class="doc-gif"></div>
                    @else
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/company-suppliers.gif') }}" alt="Supplier Management (Company)" class="doc-gif"></div>
                    @endif

                    <div class="warn-box">
                        <p class="text-sm">{!! __('doc.sup.warning') !!}</p>
                    </div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Setting')
                <section id="settings" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-cog text-primary-500 me-2"></i> {{ __('doc.settings.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.settings.desc') !!}</p>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/settings.gif') }}" alt="Settings Tabs" class="doc-gif"></div>

                    <div class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-money-bill text-green-500 me-2"></i> {{ __('doc.settings.payment.title') }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{!! __('doc.settings.payment.desc') !!}</p>
                            <ul class="list-disc list-inside text-sm text-gray-500 dark:text-gray-400 space-y-1">
                                <li>{!! __('doc.settings.payment.currency') !!}</li>
                                <li>{!! __('doc.settings.payment.tax') !!}</li>
                                <li>{!! __('doc.settings.payment.prefix') !!}</li>
                                <li>{!! __('doc.settings.payment.due') !!}</li>
                            </ul>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-file-contract text-blue-500 me-2"></i> {{ __('doc.settings.terms.title') }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{!! __('doc.settings.terms.desc') !!}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-credit-card text-purple-500 me-2"></i> {{ __('doc.settings.gateways.title') }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{!! __('doc.settings.gateways.desc') !!}</p>
                            <div class="flex flex-wrap gap-2 mb-3">
                                @foreach(['Tap','MyFatoorah','Hesabe','UPayment','Knet','Bank Transfer'] as $gw)
                                <span class="bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-xs px-3 py-1 rounded-lg font-medium">{{ $gw }}</span>
                                @endforeach
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{!! __('doc.settings.gateways.howTo') !!}</p>
                            <div class="warn-box mt-3">
                                <p class="text-sm">{!! __('doc.settings.gateways.warning') !!}</p>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-wallet text-orange-500 me-2"></i> {{ __('doc.settings.methods.title') }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{!! __('doc.settings.methods.desc') !!}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-percent text-red-500 me-2"></i> {{ __('doc.settings.charges.title') }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{!! __('doc.settings.charges.desc') !!}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                            <h3 class="text-lg font-semibold mb-2"><i class="fas fa-bell text-indigo-500 me-2"></i> {{ __('doc.settings.notifications.title') }}</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{!! __('doc.settings.notifications.desc') !!}</p>
                        </div>
                    </div>
                </section>
                @endcan

                @can('manage-system-settings')
                <section id="system-settings" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-server text-primary-500 me-2"></i> {{ __('doc.sysSettings.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.sysSettings.desc') !!}</p>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/system-settings.gif') }}" alt="System Settings" class="doc-gif"></div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-envelope text-blue-500 me-1"></i> {{ __('doc.sysSettings.email.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.sysSettings.email.desc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-1"><i class="fab fa-whatsapp text-green-500 me-1"></i> {{ __('doc.sysSettings.whatsapp.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.sysSettings.whatsapp.desc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-hotel text-purple-500 me-1"></i> {{ __('doc.sysSettings.hotel.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.sysSettings.hotel.desc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-globe text-teal-500 me-1"></i> {{ __('doc.sysSettings.country.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.sysSettings.country.desc') }}</p>
                        </div>
                    </div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Task')
                <section id="tasks" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-tasks text-primary-500 me-2"></i> {{ __('doc.tasks.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.tasks.desc') !!}</p>

                    @if($isAdmin)
                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.tasks.admin.whatCanDo') }}</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-6">
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.admin.perm1') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.admin.perm2') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.admin.perm3') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.admin.perm4') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.admin.perm5') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.admin.perm6') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.admin.perm7') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.admin.perm8') !!}</div>
                    </div>
                    @elseif($isCompany)
                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.tasks.company.whatCanDo') }}</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-6">
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.company.perm1') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.company.perm2') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.company.perm3') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.company.perm4') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.company.perm5') !!}</div>
                        <div><i class="fas fa-times text-red-400 me-1"></i> {!! __('doc.tasks.company.no1') !!}</div>
                        <div><i class="fas fa-times text-red-400 me-1"></i> {!! __('doc.tasks.company.no2') !!}</div>
                    </div>
                    @else
                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.tasks.agent.whatCanDo') }}</h3>
                    <div class="grid sm:grid-cols-2 gap-2 text-sm text-gray-600 dark:text-gray-400 mb-6">
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.agent.perm1') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.agent.perm2') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.agent.perm3') !!}</div>
                        <div><i class="fas fa-check text-green-500 me-1"></i> {!! __('doc.tasks.agent.perm4') !!}</div>
                        <div><i class="fas fa-times text-red-400 me-1"></i> {{ __('doc.tasks.agent.no1') }}</div>
                        <div><i class="fas fa-times text-red-400 me-1"></i> {!! __('doc.tasks.agent.no2') !!}</div>
                        <div><i class="fas fa-times text-red-400 me-1"></i> {{ __('doc.tasks.agent.no3') }}</div>
                    </div>
                    @endif

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.tasks.listTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        {!! __('doc.tasks.listDesc') !!}
                        @cannot('viewAny', App\Models\Agent::class)
                        {{ __('doc.tasks.listAgentNote') }}
                        @endcannot
                    </p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/task-list.gif') }}" alt="Task List View" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.tasks.createTitle') }}</h3>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.tasks.create.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <div class="text-sm">
                            <p>{!! __('doc.tasks.create.step2intro') !!}</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-2 mb-2">
                                <span class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-plane me-1"></i> Flight</span>
                                <span class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-hotel me-1"></i> Hotel</span>
                                <span class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-passport me-1"></i> Visa</span>
                                <span class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-shield-alt me-1"></i> Insurance</span>
                                <span class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-bus me-1"></i> Tour / Cruise / Car / Rail</span>
                                <span class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded px-3 py-1.5 text-xs font-medium"><i class="fas fa-ellipsis-h me-1"></i> eSIM / Event / Lounge / Ferry</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <div class="text-sm">
                            <p>{{ __('doc.tasks.create.step3intro') }}</p>
                            <ul class="list-disc list-inside text-gray-500 dark:text-gray-400 mt-1 space-y-1">
                                @can('viewAny', App\Models\Agent::class)
                                <li>{!! __('doc.tasks.create.step3agent') !!}</li>
                                @endcan
                                <li>{!! __('doc.tasks.create.step3client') !!} @cannot('viewAny', App\Models\Agent::class){{ __('doc.tasks.create.step3clientOwn') }}@endcannot</li>
                                <li>{!! __('doc.tasks.create.step3supplier') !!}</li>
                                <li>{!! __('doc.tasks.create.step3selling') !!}</li>
                                <li>{!! __('doc.tasks.create.step3cost') !!}</li>
                                <li>{!! __('doc.tasks.create.step3status') !!}</li>
                                <li>{{ __('doc.tasks.create.step3specific') }}</li>
                            </ul>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">4</span>
                        <p class="text-sm">{!! __('doc.tasks.create.step4') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/task-create.gif') }}" alt="Create Task Workflow" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3 mt-6">{{ __('doc.tasks.editTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.tasks.editDesc') !!}</p>
                    @if($isAdmin)
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.tasks.admin.financialEdit') !!}</p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/task-financial-edit.gif') }}" alt="Task Financial Edit (Admin)" class="doc-gif"></div>
                    @endif

                    @if($isAdmin)
                    <h3 class="text-lg font-semibold mb-3 mt-6">{{ __('doc.tasks.deleteTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.tasks.deleteDesc') !!}</p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/task-delete.gif') }}" alt="Delete Task" class="doc-gif"></div>
                    <div class="warn-box mb-4">
                        <p class="text-sm">{!! __('doc.tasks.deleteWarning') !!}</p>
                    </div>
                    @endif

                    @can('create', 'App\Models\Invoice')
                    <h3 class="text-lg font-semibold mb-3 mt-6">{{ __('doc.tasks.bulkTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.tasks.bulkDesc') !!}</p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/task-bulk-edit.gif') }}" alt="Bulk Edit Tasks" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3 mt-6">{{ __('doc.tasks.createInvoiceTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.tasks.createInvoiceDesc') !!}</p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/invoice-create-from-tasks.gif') }}" alt="Create Invoice from Tasks" class="doc-gif"></div>
                    @endcan
                </section>
                @endcan

                @can('viewAny', 'App\Models\Invoice')
                <section id="invoices" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-file-invoice-dollar text-primary-500 me-2"></i> {{ __('doc.inv.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.inv.desc') !!}</p>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.inv.listTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.inv.listDesc') !!}</p>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/invoice-list.gif') }}" alt="Invoice List" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.inv.createTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('doc.inv.createDesc') }}</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li>{!! __('doc.inv.createWay1') !!}</li>
                        <li>{!! __('doc.inv.createWay2') !!}</li>
                    </ul>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.inv.create.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <div class="text-sm">
                            <p>{!! __('doc.inv.create.step2intro') !!}@can('viewAny', App\Models\Agent::class){!! __('doc.inv.create.step2agent') !!}@endcan. Then configure:</p>
                            <div>{!! __('doc.inv.create.step2items') !!}</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.inv.create.step3') !!}</p>
                    </div>
                    @can('viewAny', App\Models\Agent::class)
                    <div class="info-box mb-4">
                        <p class="text-sm">{!! __('doc.inv.create.adminNote') !!}</p>
                    </div>
                    @endcan
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/invoice-create.gif') }}" alt="Create Invoice Workflow" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.inv.statusTitle') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ __('doc.inv.statusDesc') }}</p>
                    <div class="flex flex-wrap gap-3 mb-6">
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span> {!! __('doc.inv.status.unpaid') !!}</span>
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-yellow-500 inline-block"></span> {!! __('doc.inv.status.partial') !!}</span>
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-green-500 inline-block"></span> {!! __('doc.inv.status.paid') !!}</span>
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span> {!! __('doc.inv.status.paidByRefund') !!}</span>
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-orange-500 inline-block"></span> {!! __('doc.inv.status.refunded') !!}</span>
                        <span class="flex items-center gap-1.5 text-sm"><span class="w-3 h-3 rounded-full bg-purple-500 inline-block"></span> {!! __('doc.inv.status.partialRefund') !!}</span>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.inv.actionsTitle') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ __('doc.inv.actionsDesc') }}</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li>{!! __('doc.inv.action.view') !!}</li>
                        <li>{!! __('doc.inv.action.pdf') !!}</li>
                        <li>{!! __('doc.inv.action.email') !!}</li>
                        <li>{!! __('doc.inv.action.whatsapp') !!}</li>
                        <li>{!! __('doc.inv.action.lock') !!}</li>
                        <li>{!! __('doc.inv.action.delete') !!}</li>
                    </ul>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/invoice-actions.gif') }}" alt="Invoice Action Buttons" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.inv.paymentTitle') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ __('doc.inv.paymentDesc') }}</p>
                    <div class="grid sm:grid-cols-2 gap-3 mb-4">
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-check-circle text-green-500 me-1"></i> {{ __('doc.inv.payment.fullTitle') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.inv.payment.fullDesc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-adjust text-yellow-500 me-1"></i> {{ __('doc.inv.payment.partialTitle') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.inv.payment.partialDesc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-random text-blue-500 me-1"></i> {{ __('doc.inv.payment.splitTitle') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.inv.payment.splitDesc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-file-import text-purple-500 me-1"></i> {{ __('doc.inv.payment.importTitle') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.inv.payment.importDesc') }}</p>
                        </div>
                    </div>
                    <div class="info-box">
                        <p class="text-sm">{!! __('doc.inv.paymentInfo') !!}</p>
                    </div>
                </section>

                <section id="invoices-link" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-link text-primary-500 me-2"></i> {{ __('doc.invLink.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.invLink.desc') !!}</p>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.invLink.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.invLink.step2') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.invLink.step3') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/invoices-link.gif') }}" alt="Invoices Link" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Payment')
                <section id="payment-links" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-money-check-alt text-primary-500 me-2"></i> {{ __('doc.pl.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.pl.desc') !!}</p>

                    @can('viewAny', App\Models\Agent::class)
                    <div class="info-box mb-4">
                        <p class="text-sm">{!! __('doc.pl.adminNote') !!}</p>
                    </div>
                    @endcan

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.pl.pageTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.pl.pageDesc') !!}</p>
                    <div class="grid sm:grid-cols-2 gap-4 mb-6">
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-link text-blue-500 me-1"></i> {{ __('doc.pl.tab1.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.pl.tab1.desc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-file-import text-green-500 me-1"></i> {{ __('doc.pl.tab2.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.pl.tab2.desc') }}</p>
                        </div>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/payment-link.gif') }}" alt="Payment Links Page" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.pl.actionsTitle') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ __('doc.pl.actionsDesc') }}</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-6">
                        <li>{!! __('doc.pl.action.edit') !!}</li>
                        <li>{!! __('doc.pl.action.voucher') !!}</li>
                        <li>{!! __('doc.pl.action.whatsapp') !!}</li>
                        <li>{!! __('doc.pl.action.disable') !!}</li>
                        <li>{!! __('doc.pl.action.delete') !!}</li>
                    </ul>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.pl.createTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('doc.pl.createDesc') }}</p>
                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-bolt text-blue-500 me-1"></i> {{ __('doc.pl.quick.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.pl.quick.desc') }}</p>
                        </div>
                        <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-list-alt text-indigo-500 me-1"></i> {{ __('doc.pl.advanced.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{!! __('doc.pl.advanced.desc') !!}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.pl.create.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.pl.create.step2') !!}@cannot('viewAny', App\Models\Agent::class){{ __('doc.pl.create.step2own') }}@endcannot</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.pl.create.step3') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/payment-link-create.gif') }}" alt="Create Payment Link" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.pl.importTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.pl.importDesc') !!}</p>

                    <div class="info-box">
                        <p class="text-sm">{!! __('doc.pl.importInfo') !!}</p>
                    </div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Refund')
                <section id="refunds" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-undo text-primary-500 me-2"></i> {{ __('doc.ref.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.ref.desc') !!}</p>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.ref.method1Title') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">{!! __('doc.ref.method1Desc') !!}</p>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.ref.m1.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.ref.m1.step2') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.ref.m1.step3') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">4</span>
                        <p class="text-sm">{!! __('doc.ref.m1.step4') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/refund-process.gif') }}" alt="Refund List Page &amp; Create Refund" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.ref.method2Title') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">{!! __('doc.ref.method2Desc') !!}</p>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.ref.m2.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.ref.m2.step2') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.ref.m2.step3') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">4</span>
                        <p class="text-sm">{!! __('doc.ref.m2.step4') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/refund-from-tasks.gif') }}" alt="Create Refund from Task List" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.ref.calcTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('doc.ref.calcDesc') }}</p>

                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-3">
                        <h4 class="font-semibold text-green-800 dark:text-green-300 mb-2"><i class="fas fa-check-circle me-1"></i> {{ __('doc.ref.calc.paidTitle') }}</h4>
                        <p class="text-sm text-green-700 dark:text-green-400">{!! __('doc.ref.calc.paidDesc') !!}</p>
                    </div>

                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-3">
                        <h4 class="font-semibold text-yellow-800 dark:text-yellow-300 mb-2"><i class="fas fa-exclamation-triangle me-1"></i> {{ __('doc.ref.calc.unpaidTitle') }}</h4>
                        <p class="text-sm text-yellow-700 dark:text-yellow-400">{!! __('doc.ref.calc.unpaidDesc') !!}</p>
                    </div>

                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-blue-800 dark:text-blue-300 mb-2"><i class="fas fa-info-circle me-1"></i> {{ __('doc.ref.calc.partialTitle') }}</h4>
                        <p class="text-sm text-blue-700 dark:text-blue-400 mb-2">{!! __('doc.ref.calc.partialDesc') !!}</p>
                        <ul class="text-sm text-blue-700 dark:text-blue-400 list-disc ps-5 space-y-1">
                            <li>{!! __('doc.ref.calc.partial1') !!}</li>
                            <li>{!! __('doc.ref.calc.partial2') !!}</li>
                            <li>{!! __('doc.ref.calc.partial3') !!}</li>
                        </ul>
                    </div>

                    <div class="warn-box">
                        <p class="text-sm">{!! __('doc.ref.perTaskInfo') !!}</p>
                    </div>

                    @cannot('viewAny', App\Models\Agent::class)
                    <div class="info-box">
                        <p class="text-sm">{!! __('doc.ref.agentNote') !!}</p>
                    </div>
                    @endcannot
                </section>
                @endcan

                @can('viewAny', App\Models\Payment::class)
                <section id="outstanding" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-exclamation-circle text-primary-500 me-2"></i> {{ __('doc.out.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.out.desc') !!}</p>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.out.whatTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">{!! __('doc.out.whatDesc') !!}</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li>{!! __('doc.out.item1') !!}</li>
                        <li>{!! __('doc.out.item2') !!}</li>
                        <li>{!! __('doc.out.item3') !!}</li>
                    </ul>

                    @cannot('viewAny', App\Models\Agent::class)
                    <div class="info-box">
                        <p class="text-sm">{!! __('doc.out.agentNote') !!}</p>
                    </div>
                    @endcannot
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/outstanding-reminders.gif') }}" alt="Outstanding Pending Actions" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\AutoBilling')
                <section id="auto-billing" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-sync-alt text-primary-500 me-2"></i> {{ __('doc.ab.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.ab.desc') !!}</p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.ab.nav') !!}</p>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.ab.howTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">{!! __('doc.ab.howDesc') !!}</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-user-edit text-blue-500 me-1"></i> {{ __('doc.ab.cond1.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.ab.cond1.desc') }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-user-tie text-green-500 me-1"></i> {{ __('doc.ab.cond2.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.ab.cond2.desc') }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-building text-purple-500 me-1"></i> {{ __('doc.ab.cond3.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.ab.cond3.desc') }}</p>
                        </div>
                    </div>
                    <div class="info-box mb-4">
                        <p class="text-sm">{!! __('doc.ab.condInfo') !!}</p>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.ab.configTitle') }}</h3>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li>{!! __('doc.ab.config1') !!}</li>
                        <li>{!! __('doc.ab.config2') !!}</li>
                        <li>{!! __('doc.ab.config3') !!}</li>
                        <li>{!! __('doc.ab.config4') !!}</li>
                        <li>{!! __('doc.ab.config5') !!}</li>
                        <li>{!! __('doc.ab.config6') !!}</li>
                        <li>{!! __('doc.ab.config7') !!}</li>
                    </ul>

                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/auto-billing.gif') }}" alt="Auto Billing Setup" class="doc-gif"></div>

                    <div class="warn-box">
                        <p class="text-sm">{!! __('doc.ab.warning') !!}</p>
                    </div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Invoice')
                <section id="reminders" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-clock text-primary-500 me-2"></i> {{ __('doc.rem.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.rem.desc') !!}</p>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.rem.tabsTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('doc.rem.tabsDesc') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-file-invoice text-blue-500 me-1"></i> {{ __('doc.rem.tab1.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rem.tab1.desc') }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-link text-green-500 me-1"></i> {{ __('doc.rem.tab2.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rem.tab2.desc') }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-history text-purple-500 me-1"></i> {{ __('doc.rem.tab3.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rem.tab3.desc') }}</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.rem.sendTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">{!! __('doc.rem.sendDesc') !!}</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li>{!! __('doc.rem.send1') !!}</li>
                        <li>{!! __('doc.rem.send2') !!}</li>
                        <li>{!! __('doc.rem.send3') !!}</li>
                    </ul>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.rem.modeTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('doc.rem.modeDesc') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-paper-plane text-blue-500 me-1"></i> {{ __('doc.rem.oneTime.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rem.oneTime.desc') }}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                            <h4 class="font-semibold text-sm mb-1"><i class="fas fa-redo text-orange-500 me-1"></i> {{ __('doc.rem.autoRepeat.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{!! __('doc.rem.autoRepeat.desc') !!}</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.rem.scheduleTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">{!! __('doc.rem.scheduleDesc') !!}</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li>{!! __('doc.rem.schedule1') !!}</li>
                        <li>{!! __('doc.rem.schedule2') !!}</li>
                        <li>{!! __('doc.rem.schedule3') !!}</li>
                    </ul>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.rem.presets') !!}</p>

                    <div class="info-box mb-4">
                        <p class="text-sm">{!! __('doc.rem.info') !!}</p>
                    </div>

                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/reminders.gif') }}" alt="Reminders Page" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\CoaCategory')
                <section id="accounting" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-book text-primary-500 me-2"></i> {{ __('doc.acc.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.acc.desc') !!}</p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.acc.nav') !!}</p>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/coa.gif') }}" alt="Accounting Overview" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.acc.autoTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-2">{{ __('doc.acc.autoDesc') }}</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li>{!! __('doc.acc.auto1') !!}</li>
                        <li>{!! __('doc.acc.auto2') !!}</li>
                        <li>{!! __('doc.acc.auto3') !!}</li>
                    </ul>

                    <h3 class="text-lg font-semibold mb-3 mt-6">{{ __('doc.acc.rvTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.acc.rvDesc') !!}</p>
                    <h4 class="font-semibold text-sm mb-2">{{ __('doc.acc.rvHow') }}</h4>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.acc.rv.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.acc.rv.step2') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.acc.rv.step3') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">4</span>
                        <p class="text-sm">{!! __('doc.acc.rv.step4') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">5</span>
                        <p class="text-sm">{!! __('doc.acc.rv.step5') !!}</p>
                    </div>
                    <div class="info-box mb-4">
                        <p class="text-sm">{!! __('doc.acc.rvInfo') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/receipt-voucher.gif') }}" alt="Receipt Voucher" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.acc.pvTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.acc.pvDesc') !!}</p>
                    <h4 class="font-semibold text-sm mb-2">{{ __('doc.acc.pvHow') }}</h4>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.acc.pv.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.acc.pv.step2') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.acc.pv.step3') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">4</span>
                        <p class="text-sm">{!! __('doc.acc.pv.step4') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">5</span>
                        <p class="text-sm">{!! __('doc.acc.pv.step5') !!}</p>
                    </div>
                    <div class="warn-box mb-4">
                        <p class="text-sm">{!! __('doc.acc.pvWarning') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/payment-voucher.gif') }}" alt="Payment Voucher" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.acc.jeTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.acc.jeDesc') !!}</p>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.acc.summaryTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.acc.summaryDesc') !!}</p>

                    <h3 class="text-lg font-semibold mb-3 mt-6">{{ __('doc.acc.recTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.acc.recDesc') !!}</p>
                    <h4 class="font-semibold text-sm mb-2">{{ __('doc.acc.recHow') }}</h4>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.acc.rec.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.acc.rec.step2') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.acc.rec.step3') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">4</span>
                        <p class="text-sm">{!! __('doc.acc.rec.step4') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">5</span>
                        <p class="text-sm">{!! __('doc.acc.rec.step5') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/receivable-details.gif') }}" alt="Receivable Details" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.acc.payTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.acc.payDesc') !!}</p>
                    <h4 class="font-semibold text-sm mb-2">{{ __('doc.acc.payHow') }}</h4>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.acc.pay.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.acc.pay.step2') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.acc.pay.step3') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">4</span>
                        <p class="text-sm">{!! __('doc.acc.pay.step4') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-6"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/payable-details.gif') }}" alt="Payable Details" class="doc-gif"></div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.acc.lockTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.acc.lockDesc') !!}</p>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">{!! __('doc.acc.lockDashDesc') !!}</p>

                    <h4 class="font-semibold text-sm mb-2">{{ __('doc.acc.lockHow') }}</h4>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('doc.acc.lockMethods') }}</p>
                    <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-4">
                        <li>{!! __('doc.acc.lock1') !!}</li>
                        <li>{!! __('doc.acc.lock2') !!}</li>
                    </ul>

                    <h4 class="font-semibold text-sm mb-2">{{ __('doc.acc.unlockTitle') }}</h4>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.acc.unlockDesc') !!}</p>
                    <div class="warn-box mb-4">
                        <p class="text-sm">{!! __('doc.acc.lockWarning') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/lock-management.gif') }}" alt="Lock Management" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', App\Models\CurrencyExchange::class)
                <section id="currency-exchange" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-exchange-alt text-primary-500 me-2"></i> {{ __('doc.ce.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.ce.desc') !!}</p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('doc.ce.usage') }}
                    </p>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.ce.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.ce.step2') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.ce.step3') !!}</p>
                    </div>
                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/currency-exchange.gif') }}" alt="Currency Exchange" class="doc-gif"></div>
                </section>
                @endcan

                @can('viewAny', 'App\Models\Report')
                <section id="reports" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-chart-bar text-primary-500 me-2"></i> {{ __('doc.rpt.title') }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.rpt.desc') !!}</p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">{!! __('doc.rpt.filters') !!}</p>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.rpt.availableTitle') }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-3">{!! __('doc.rpt.availableDesc') !!}</p>

                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-chart-line text-blue-500 me-1"></i> {{ __('doc.rpt.dailySales.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rpt.dailySales.desc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-balance-scale text-green-500 me-1"></i> {{ __('doc.rpt.pnl.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rpt.pnl.desc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-file-invoice-dollar text-purple-500 me-1"></i> {{ __('doc.rpt.paidAp.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{!! __('doc.rpt.paidAp.desc') !!}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-exclamation-circle text-red-500 me-1"></i> {{ __('doc.rpt.unpaidAp.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{!! __('doc.rpt.unpaidAp.desc') !!}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-tasks text-orange-500 me-1"></i> {{ __('doc.rpt.task.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rpt.task.desc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-user-friends text-teal-500 me-1"></i> {{ __('doc.rpt.client.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rpt.client.desc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-hand-holding-usd text-yellow-500 me-1"></i> {{ __('doc.rpt.creditors.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rpt.creditors.desc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-university text-indigo-500 me-1"></i> {{ __('doc.rpt.bank.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rpt.bank.desc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-credit-card text-pink-500 me-1"></i> {{ __('doc.rpt.gateways.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rpt.gateways.desc') }}</p>
                        </div>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                            <h4 class="font-semibold text-sm mb-2"><i class="fas fa-calculator text-gray-500 me-1"></i> {{ __('doc.rpt.trial.title') }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('doc.rpt.trial.desc') }}</p>
                        </div>
                    </div>

                    <h3 class="text-lg font-semibold mb-3">{{ __('doc.rpt.howTitle') }}</h3>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">1</span>
                        <p class="text-sm">{!! __('doc.rpt.how.step1') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-2">
                        <span class="step-number">2</span>
                        <p class="text-sm">{!! __('doc.rpt.how.step2') !!}</p>
                    </div>
                    <div class="flex items-start gap-3 mb-4">
                        <span class="step-number">3</span>
                        <p class="text-sm">{!! __('doc.rpt.how.step3') !!}</p>
                    </div>

                    <div class="doc-gif-wrap mb-4"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/reports.gif') }}" alt="Reports Navigation" class="doc-gif"></div>

                    <div class="info-box">
                        <p class="text-sm">{!! __('doc.rpt.info') !!}</p>
                    </div>
                </section>
                @endcan

                <section id="faq" class="mb-16 scroll-mt-24">
                    <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
                        <i class="fas fa-question-circle text-primary-500 me-2"></i> {{ __('doc.faq.title') }}
                    </h2>
                    <div class="space-y-3">
                        @can('viewAny', 'App\Models\User')
                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-start p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">{{ __('doc.faq.resetPassword.q') }}</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">{!! __('doc.faq.resetPassword.a') !!}</div>
                        </div>
                        @endcan

                        @cannot('viewAny', App\Models\Supplier::class)
                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-start p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">{{ __('doc.faq.supplierMissing.q') }}</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">{!! __('doc.faq.supplierMissing.a') !!}</div>
                        </div>
                        @endcannot

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-start p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">{{ __('doc.faq.clientLogin.q') }}</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">{!! __('doc.faq.clientLogin.a') !!}</div>
                        </div>

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-start p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">{{ __('doc.faq.partialPayment.q') }}</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">{!! __('doc.faq.partialPayment.a') !!}</div>
                        </div>

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-start p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">{{ __('doc.faq.lockInvoice.q') }}</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">{!! __('doc.faq.lockInvoice.a') !!}</div>
                        </div>

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-start p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">{{ __('doc.faq.plVsInvoice.q') }}</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">{!! __('doc.faq.plVsInvoice.a') !!}</div>
                        </div>

                        @cannot('viewAny', App\Models\Agent::class)
                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-start p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">{{ __('doc.faq.cantDelete.q') }}</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">{!! __('doc.faq.cantDelete.a') !!}</div>
                        </div>
                        @endcannot

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-start p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">{{ __('doc.faq.profit.q') }}</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">{!! __('doc.faq.profit.a') !!}</div>
                        </div>

                        @can('viewAny', 'App\Models\Company')
                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-start p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">{{ __('doc.faq.switchCompany.q') }}</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">{!! __('doc.faq.switchCompany.a') !!}</div>
                        </div>
                        @endcan

                        @can('viewAny', 'App\Models\Setting')
                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-start p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">{{ __('doc.faq.whatsapp.q') }}</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">
                                @if($isAdmin)
                                {!! __('doc.faq.whatsapp.admin') !!}
                                @else
                                {!! __('doc.faq.whatsapp.other') !!}
                                @endif
                            </div>
                        </div>
                        @endcan

                        <div x-data="{ open: false }" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
                            <button @click="open = !open" class="w-full text-start p-4 flex justify-between items-center">
                                <span class="font-medium text-sm">{{ __('doc.faq.forbidden.q') }}</span>
                                <i class="fas fa-chevron-down transition-transform text-xs" :class="open && 'rotate-180'"></i>
                            </button>
                            <div x-show="open" x-transition class="px-4 pb-4 text-sm text-gray-600 dark:text-gray-400">{!! __('doc.faq.forbidden.a') !!}</div>
                        </div>
                    </div>
                </section>

                <div class="text-center py-8 border-t border-gray-200 dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">
                    <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('doc.footer.rights') }}</p>
                </div>

            </div>
        </div>
    </div>

    <button @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
            x-show="scrollProgress > 10" x-transition
            class="fixed bottom-8 end-8 bg-primary-500 text-white w-10 h-10 rounded-full shadow-lg flex items-center justify-center hover:bg-primary-600 transition-colors z-30">
        <i class="fas fa-chevron-up"></i>
    </button>

    <script>
        function docsApp() {
            const role = @json($roleName);
            const p = @json($perms);

            const groups = [];

            groups.push({
                label: '{{ __("doc.nav.gettingStarted") }}',
                links: [
                    { id: 'getting-started', title: '{{ __("doc.nav.link.gettingStarted") }}', icon: 'fas fa-rocket' },
                    ...(p.viewRoles ? [{ id: 'role-overview', title: '{{ __("doc.nav.link.rolesOverview") }}', icon: 'fas fa-shield-halved' }] : []),
                ]
            });

            const setupLinks = [];
            if (p.viewUsers) {
                setupLinks.push({ id: 'user-management', title: '{{ __("doc.nav.link.userManagement") }}', icon: 'fas fa-users' });
            } else if (p.viewClients) {
                setupLinks.push({ id: 'my-clients', title: '{{ __("doc.nav.link.yourClients") }}', icon: 'fas fa-user' });
            }
            if (p.viewSuppliers) {
                setupLinks.push({ id: 'suppliers', title: '{{ __("doc.nav.link.suppliers") }}', icon: 'fas fa-handshake' });
            }
            if (p.viewSettings) {
                setupLinks.push({ id: 'settings', title: '{{ __("doc.nav.link.settings") }}', icon: 'fas fa-cog' });
            }
            if (p.manageSystemSettings) {
                setupLinks.push({ id: 'system-settings', title: '{{ __("doc.nav.link.systemSettings") }}', icon: 'fas fa-server' });
            }
            if (setupLinks.length > 0) {
                groups.push({ label: '{{ __("doc.nav.setup") }}', links: setupLinks });
            }

            const opsLinks = [];
            if (p.viewTasks) {
                opsLinks.push({ id: 'tasks', title: '{{ __("doc.nav.link.tasks") }}', icon: 'fas fa-tasks' });
            }
            if (p.viewInvoices) {
                opsLinks.push(
                    { id: 'invoices', title: '{{ __("doc.nav.link.invoices") }}', icon: 'fas fa-file-invoice-dollar' },
                    { id: 'invoices-link', title: '{{ __("doc.nav.link.invoicesLink") }}', icon: 'fas fa-link' },
                );
            }
            if (p.viewPayments) {
                opsLinks.push({ id: 'payment-links', title: '{{ __("doc.nav.link.paymentLinks") }}', icon: 'fas fa-money-check-alt' });
            }
            if (p.viewRefunds) {
                opsLinks.push({ id: 'refunds', title: '{{ __("doc.nav.link.refunds") }}', icon: 'fas fa-undo' });
            }
            if (p.viewInvoices) {
                opsLinks.push({ id: 'reminders', title: '{{ __("doc.nav.link.reminders") }}', icon: 'fas fa-clock' });
            }
            if (opsLinks.length > 0) {
                groups.push({ label: '{{ __("doc.nav.operations") }}', links: opsLinks });
            }

            const finLinks = [];
            if (p.viewPayments) {
                finLinks.push({ id: 'outstanding', title: '{{ __("doc.nav.link.outstanding") }}', icon: 'fas fa-exclamation-circle' });
            }
            if (p.viewAutoBilling) {
                finLinks.push({ id: 'auto-billing', title: '{{ __("doc.nav.link.autoBilling") }}', icon: 'fas fa-sync-alt' });
            }
            if (p.viewCoa) {
                finLinks.push({ id: 'accounting', title: '{{ __("doc.nav.link.accounting") }}', icon: 'fas fa-book' });
            }
            if (p.viewCurrencyExchange) {
                finLinks.push({ id: 'currency-exchange', title: '{{ __("doc.nav.link.currencyExchange") }}', icon: 'fas fa-exchange-alt' });
            }
            if (p.viewReports) {
                finLinks.push({ id: 'reports', title: '{{ __("doc.nav.link.reports") }}', icon: 'fas fa-chart-bar' });
            }
            if (finLinks.length > 0) {
                groups.push({ label: '{{ __("doc.nav.finance") }}', links: finLinks });
            }

            groups.push({
                label: '{{ __("doc.nav.help") }}',
                links: [
                    { id: 'faq', title: '{{ __("doc.nav.link.faq") }}', icon: 'fas fa-question-circle' },
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
                    { id: 'getting-started', title: '{{ __("doc.nav.link.gettingStarted") }}', keywords: 'login dashboard start' },
                    { id: 'role-overview', title: '{{ __("doc.nav.link.rolesOverview") }}', keywords: 'role permission admin company agent' },
                    { id: 'user-management', title: '{{ __("doc.nav.link.userManagement") }}', keywords: 'user company branch agent client accountant create' },
                    { id: 'my-clients', title: '{{ __("doc.nav.link.yourClients") }}', keywords: 'client add register' },
                    { id: 'suppliers', title: '{{ __("doc.nav.link.suppliers") }}', keywords: 'supplier activate service' },
                    { id: 'settings', title: '{{ __("doc.nav.link.settings") }}', keywords: 'settings payment gateway method terms notification' },
                    { id: 'system-settings', title: '{{ __("doc.nav.link.systemSettings") }}', keywords: 'system email whatsapp hotel country' },
                    { id: 'tasks', title: '{{ __("doc.nav.link.tasks") }}', keywords: 'task booking flight hotel visa insurance create' },
                    { id: 'invoices', title: '{{ __("doc.nav.link.invoices") }}', keywords: 'invoice create send pdf lock status partial split' },
                    { id: 'invoices-link', title: '{{ __("doc.nav.link.invoicesLink") }}', keywords: 'invoice link group share multiple' },
                    { id: 'payment-links', title: '{{ __("doc.nav.link.paymentLinks") }}', keywords: 'payment link share online full' },
                    { id: 'refunds', title: '{{ __("doc.nav.link.refunds") }}', keywords: 'refund cancel money back' },
                    { id: 'reminders', title: '{{ __("doc.nav.link.reminders") }}', keywords: 'reminder overdue due date follow up' },
                    { id: 'outstanding', title: '{{ __("doc.nav.link.outstanding") }}', keywords: 'outstanding unpaid balance pending' },
                    { id: 'auto-billing', title: '{{ __("doc.nav.link.autoBilling") }}', keywords: 'auto billing automatic invoice' },
                    { id: 'accounting', title: '{{ __("doc.nav.link.accounting") }}', keywords: 'coa chart accounts voucher receipt payment journal receivable payable lock management' },
                    { id: 'currency-exchange', title: '{{ __("doc.nav.link.currencyExchange") }}', keywords: 'currency exchange rate foreign' },
                    { id: 'reports', title: '{{ __("doc.nav.link.reports") }}', keywords: 'report sales profit agent financial commission' },
                    { id: 'faq', title: '{{ __("doc.nav.link.faq") }}', keywords: 'faq question help' },
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
