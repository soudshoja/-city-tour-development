<x-documentation-layout>

@php
    $user = auth()->user();
    $roleName = $user->roles->first()?->name ?? 'agent';
    $isAdmin = $user->role_id == \App\Models\Role::ADMIN;
    $isCompany = $user->role_id == \App\Models\Role::COMPANY;
    $isBranch = $user->role_id == \App\Models\Role::BRANCH;
    $isAgent = $user->role_id == \App\Models\Role::AGENT;
    $isAccountant = $user->role_id == \App\Models\Role::ACCOUNTANT;
@endphp

@push('header-badge')
    <span class="text-xs bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 px-2 py-0.5 sm:py-1 rounded-full font-semibold uppercase flex-shrink-0">{{ $roleName }}</span>
@endpush

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
</x-documentation-layout>
