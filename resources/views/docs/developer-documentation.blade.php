<x-documentation-layout>
    <div id="welcome" class="bg-gradient-to-r from-primary-600 to-primary-500 rounded-xl shadow-lg p-5 sm:p-8 mb-8 sm:mb-12 text-white">
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold mb-3">
            {{ __('devdoc.welcome.title') }}
        </h1>
        <p class="text-sm opacity-75 font-medium uppercase tracking-wide mb-1">{{ __('devdoc.welcome.subtitle') }}</p>
        <p class="text-lg opacity-90 max-w-3xl">{{ __('devdoc.welcome.desc') }}</p>
    </div>

    <section id="tech-stack" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-layer-group text-primary-500 me-2"></i> {{ __('devdoc.techStack.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.techStack.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.techStack.backendTitle') }}</h3>
        <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-6">
            @foreach(__('devdoc.techStack.backendItems') as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.techStack.frontendTitle') }}</h3>
        <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 mb-6">
            @foreach(__('devdoc.techStack.frontendItems') as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.techStack.setupTitle') }}</h3>
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-5 mb-6">
            <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 space-y-2">
                @foreach(__('devdoc.techStack.setupSteps') as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.techStack.envTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.techStack.envDesc') }}</p>
        <div class="info-box">
            <ul class="list-disc list-inside text-sm space-y-1">
                @foreach(__('devdoc.techStack.envItems') as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section id="database" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-database text-primary-500 me-2"></i> {{ __('devdoc.database.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.database.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.database.coreModelsTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.database.coreModelsDesc') }}</p>
        <div class="grid gap-3 mb-6">
            @foreach(__('devdoc.database.models') as $model)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-cube text-primary-400 me-1"></i> {{ $model }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.database.taskModelsTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.database.taskModelsDesc') }}</p>
        <div class="grid gap-3 mb-6">
            @foreach(__('devdoc.database.taskModels') as $model)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-plane text-primary-400 me-1"></i> {{ $model }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.database.financialModelsTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.database.financialModelsDesc') }}</p>
        <div class="grid gap-3 mb-6">
            @foreach(__('devdoc.database.financialModels') as $model)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-coins text-primary-400 me-1"></i> {{ $model }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.database.migrationsTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.database.migrationsDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.database.tipsTitle') }}</h3>
        <div class="info-box">
            <ul class="list-disc list-inside text-sm space-y-1">
                @foreach(__('devdoc.database.tips') as $tip)
                    <li>{{ $tip }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Section 3: Roles & Permissions --}}
    <section id="roles" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-shield-halved text-primary-500 me-2"></i> {{ __('devdoc.roles.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.roles.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.roles.rolesTitle') }}</h3>
        <div class="grid gap-3 mb-6">
            @foreach(__('devdoc.roles.rolesList') as $key => $role)
                @php
                    $colors = ['admin' => 'amber', 'company' => 'blue', 'branch' => 'green', 'agent' => 'indigo', 'accountant' => 'pink', 'client' => 'purple'];
                    $color = $colors[$key] ?? 'gray';
                @endphp
                <div class="bg-{{ $color }}-50 dark:bg-{{ $color }}-900/20 border border-{{ $color }}-200 dark:border-{{ $color }}-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <span class="bg-{{ $color }}-200 dark:bg-{{ $color }}-800 text-{{ $color }}-800 dark:text-{{ $color }}-200 text-xs font-bold px-2 py-0.5 rounded me-2">{{ strtoupper($key) }}</span>
                    {{ $role }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.roles.permissionsTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.roles.permissionsDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.roles.policiesTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.roles.policiesDesc') }}</p>
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono leading-relaxed">{{ __('devdoc.roles.policiesList') }}</p>
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.roles.howToTitle') }}</h3>
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-5">
            <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 space-y-2">
                @foreach(__('devdoc.roles.howToSteps') as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Section 4: Authentication & Security --}}
    <section id="auth" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-lock text-primary-500 me-2"></i> {{ __('devdoc.auth.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.auth.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.auth.loginTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.auth.loginDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.auth.google2faTitle') }}</h3>
        <div class="warn-box mb-4">
            <p class="text-sm"><i class="fas fa-exclamation-triangle me-1"></i> {{ __('devdoc.auth.google2faDesc') }}</p>
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.auth.encryptionTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.auth.encryptionDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.auth.passwordTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.auth.passwordDesc') }}</p>
    </section>

    {{-- Section 5: Key Business Flows --}}
    <section id="business-flows" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-diagram-project text-primary-500 me-2"></i> {{ __('devdoc.businessFlows.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.businessFlows.desc') }}</p>

        <div class="space-y-6">
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-5">
                <h3 class="text-lg font-semibold mb-2"><i class="fas fa-plane text-blue-500 me-2"></i>{{ __('devdoc.businessFlows.taskFlowTitle') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('devdoc.businessFlows.taskFlowDesc') }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-5">
                <h3 class="text-lg font-semibold mb-2"><i class="fas fa-file-invoice-dollar text-green-500 me-2"></i>{{ __('devdoc.businessFlows.invoiceFlowTitle') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('devdoc.businessFlows.invoiceFlowDesc') }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-5">
                <h3 class="text-lg font-semibold mb-2"><i class="fas fa-credit-card text-purple-500 me-2"></i>{{ __('devdoc.businessFlows.paymentFlowTitle') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('devdoc.businessFlows.paymentFlowDesc') }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-5">
                <h3 class="text-lg font-semibold mb-2"><i class="fas fa-undo text-red-500 me-2"></i>{{ __('devdoc.businessFlows.refundFlowTitle') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('devdoc.businessFlows.refundFlowDesc') }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-5">
                <h3 class="text-lg font-semibold mb-2"><i class="fas fa-book text-amber-500 me-2"></i>{{ __('devdoc.businessFlows.accountingFlowTitle') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('devdoc.businessFlows.accountingFlowDesc') }}</p>
            </div>
        </div>
    </section>

    {{-- Section 6: Services Layer --}}
    <section id="services" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-cubes text-primary-500 me-2"></i> {{ __('devdoc.services.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.services.desc') }}</p>

        <div class="grid gap-3 mb-6">
            @foreach(__('devdoc.services.list') as $service)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-cube text-primary-400 me-1"></i> {{ $service }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.services.conventionTitle') }}</h3>
        <div class="info-box">
            <p class="text-sm">{{ __('devdoc.services.conventionDesc') }}</p>
        </div>
    </section>

    {{-- Section 7: Artisan Commands --}}
    <section id="commands" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-terminal text-primary-500 me-2"></i> {{ __('devdoc.commands.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.commands.desc') }}</p>

        <div class="info-box mb-8">
            <p class="text-sm"><i class="fas fa-info-circle me-1"></i> {{ __('devdoc.commands.typesOverview') }}</p>
        </div>

        {{-- Cron Job Commands --}}
        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-clock text-blue-500 me-2"></i>{{ __('devdoc.commands.cronTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.commands.cronDesc') }}</p>
        <div class="space-y-4 mb-10">
            @foreach(__('devdoc.commands.cronCommands') as $key => $cmd)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border-s-4 border-blue-500">
                    <div class="flex flex-wrap items-center gap-2 mb-2">
                        <span class="bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200 text-xs font-mono px-2 py-1 rounded">{{ $cmd['schedule'] }}</span>
                        <span class="bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs px-2 py-0.5 rounded">{{ $cmd['server'] }}</span>
                    </div>
                    <p class="font-mono text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">php artisan {{ $cmd['name'] }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ $cmd['desc'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-500"><strong>{{ __('devdoc.commands.whyLabel') }}:</strong> {{ $cmd['why'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Manual / Utility Commands --}}
        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-wrench text-amber-500 me-2"></i>{{ __('devdoc.commands.manualTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.commands.manualDesc') }}</p>
        <div class="space-y-4 mb-10">
            @foreach(__('devdoc.commands.manualCommands') as $key => $cmd)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border-s-4 border-amber-500">
                    <p class="font-mono text-sm font-semibold text-gray-800 dark:text-gray-200 mb-1">php artisan {{ $cmd['name'] }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ $cmd['desc'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-500"><strong>{{ __('devdoc.commands.whenLabel') }}:</strong> {{ $cmd['when'] }}</p>
                    @if(isset($cmd['example']))
                        <div class="mt-2 bg-gray-900 dark:bg-gray-950 rounded p-2">
                            <code class="text-xs text-green-400 font-mono">$ {{ $cmd['example'] }}</code>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- How to Add Cron Job in cPanel --}}
        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-plus-circle text-green-500 me-2"></i>{{ __('devdoc.commands.cronGuideTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.commands.cronGuideDesc') }}</p>
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-5 mb-4">
            <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 space-y-3">
                @foreach(__('devdoc.commands.cronGuideSteps') as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </div>

        <h4 class="text-base font-semibold mb-2">{{ __('devdoc.commands.cronFormatTitle') }}</h4>
        <div class="bg-gray-900 dark:bg-gray-950 rounded-lg p-4 mb-4 overflow-x-auto">
            <pre class="text-xs text-green-400 font-mono leading-relaxed">{{ __('devdoc.commands.cronFormatExample') }}</pre>
        </div>

        <div class="warn-box mb-4">
            <p class="text-sm"><i class="fas fa-exclamation-triangle me-1"></i> {{ __('devdoc.commands.cronGuideWarning') }}</p>
        </div>

        {{-- Cron Job GIF --}}
        <div class="doc-gif-wrap"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/cron-job.gif') }}" alt="{{ __('devdoc.commands.cronGuideGifPlaceholder') }}" class="doc-gif"></div>
    </section>

    {{-- Section 8: Jobs & Queue --}}
    <section id="jobs" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-clock-rotate-left text-primary-500 me-2"></i> {{ __('devdoc.jobs.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.jobs.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.jobs.activeTitle') }}</h3>
        <div class="grid gap-3 mb-6">
            @foreach(__('devdoc.jobs.activeJobs') as $job)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-gear text-primary-400 me-1"></i> {{ $job }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.jobs.queueTitle') }}</h3>
        <div class="info-box mb-4">
            <p class="text-sm">{{ __('devdoc.jobs.queueDesc') }}</p>
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.jobs.failedTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.jobs.failedDesc') }}</p>
    </section>

    {{-- Section 9: Payment Gateways --}}
    <section id="payments" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-credit-card text-primary-500 me-2"></i> {{ __('devdoc.payments.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.payments.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.payments.gatewaysTitle') }}</h3>
        <div class="grid gap-3 mb-6">
            @foreach(__('devdoc.payments.gateways') as $key => $gateway)
                @php
                    $gwColors = ['tap' => 'blue', 'myfatoorah' => 'green', 'hesabe' => 'purple', 'upayment' => 'amber', 'knet' => 'red'];
                    $gwColor = $gwColors[$key] ?? 'gray';
                @endphp
                <div class="bg-{{ $gwColor }}-50 dark:bg-{{ $gwColor }}-900/20 border border-{{ $gwColor }}-200 dark:border-{{ $gwColor }}-800 rounded-lg p-4 text-sm text-gray-600 dark:text-gray-400">
                    <span class="bg-{{ $gwColor }}-200 dark:bg-{{ $gwColor }}-800 text-{{ $gwColor }}-800 dark:text-{{ $gwColor }}-200 text-xs font-bold px-2 py-0.5 rounded me-2">{{ strtoupper($key) }}</span>
                    {{ $gateway }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.payments.architectureTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.payments.architectureDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.payments.callbackTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.payments.callbackDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.payments.perCompanyTitle') }}</h3>
        <div class="info-box">
            <p class="text-sm">{{ __('devdoc.payments.perCompanyDesc') }}</p>
        </div>
    </section>

    {{-- Section 11: GraphQL API --}}
    <section id="graphql" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-circle-nodes text-primary-500 me-2"></i> {{ __('devdoc.graphql.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.graphql.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.graphql.schemaTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.graphql.schemaDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.graphql.endpointTitle') }}</h3>
        <div class="info-box mb-4">
            <p class="text-sm">{{ __('devdoc.graphql.endpointDesc') }}</p>
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.graphql.authTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.graphql.authDesc') }}</p>
    </section>

    {{-- Section 12: Email & Communication --}}
    <section id="email" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-envelope text-primary-500 me-2"></i> {{ __('devdoc.email.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.email.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.email.outgoingTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.email.outgoingDesc') }}</p>
        <div class="grid gap-2 mb-6">
            @foreach(__('devdoc.email.mailables') as $mail)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-paper-plane text-primary-400 me-1"></i> {{ $mail }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.email.incomingTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.email.incomingDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.email.imapTitle') }}</h3>
        <div class="info-box">
            <p class="text-sm">{{ __('devdoc.email.imapDesc') }}</p>
        </div>
    </section>

    {{-- Section 13: File Processing --}}
    <section id="file-processing" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-file-pdf text-primary-500 me-2"></i> {{ __('devdoc.fileProcessing.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.fileProcessing.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.fileProcessing.pdfTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.fileProcessing.pdfDesc') }}</p>
        <div class="grid gap-2 mb-6">
            @foreach(__('devdoc.fileProcessing.pdfLibraries') as $lib)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-file-code text-primary-400 me-1"></i> {{ $lib }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.fileProcessing.airTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.fileProcessing.airDesc') }}</p>
        <div class="grid gap-2 mb-6">
            @foreach(__('devdoc.fileProcessing.airServices') as $svc)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-cog text-primary-400 me-1"></i> {{ $svc }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.fileProcessing.ocrTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.fileProcessing.ocrDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.fileProcessing.excelTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.fileProcessing.excelDesc') }}</p>
    </section>

    {{-- Section 14: AI Integration --}}
    <section id="ai" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-robot text-primary-500 me-2"></i> {{ __('devdoc.ai.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.ai.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.ai.providerTitle') }}</h3>
        <div class="warn-box mb-4">
            <p class="text-sm"><i class="fas fa-info-circle me-1"></i> {{ __('devdoc.ai.providerDesc') }}</p>
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.ai.architectureTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.ai.architectureDesc') }}</p>
        <div class="grid gap-2 mb-6">
            @foreach(__('devdoc.ai.architectureItems') as $item)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ $item }}</div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.ai.featuresTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.ai.featuresDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.ai.configTitle') }}</h3>
        <div class="info-box">
            <p class="text-sm">{{ __('devdoc.ai.configDesc') }}</p>
        </div>
    </section>

    {{-- Section 15: Frontend Patterns --}}
    <section id="frontend" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-palette text-primary-500 me-2"></i> {{ __('devdoc.frontend.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.frontend.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.frontend.componentsTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.frontend.componentsDesc') }}</p>
        <div class="grid gap-2 mb-6">
            @foreach(__('devdoc.frontend.components') as $comp)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-puzzle-piece text-primary-400 me-1"></i> {{ $comp }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.frontend.layoutsTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.frontend.layoutsDesc') }}</p>
        <div class="grid gap-2 mb-6">
            @foreach(__('devdoc.frontend.layouts') as $layout)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-columns text-primary-400 me-1"></i> {{ $layout }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.frontend.livewireTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.frontend.livewireDesc') }}</p>
        <div class="grid gap-2 mb-6">
            @foreach(__('devdoc.frontend.livewire') as $lw)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-bolt text-primary-400 me-1"></i> {{ $lw }}
                </div>
            @endforeach
        </div>
    </section>

    {{-- Section 16: Localization --}}
    <section id="localization" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-language text-primary-500 me-2"></i> {{ __('devdoc.localization.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.localization.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.localization.structureTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.localization.structureDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.localization.howTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.localization.howDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.localization.rtlTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.localization.rtlDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.localization.addingTitle') }}</h3>
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-5">
            <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 space-y-2">
                @foreach(__('devdoc.localization.addingSteps') as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Section 17: Enums --}}
    <section id="enums" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-list-ol text-primary-500 me-2"></i> {{ __('devdoc.enums.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.enums.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.enums.listTitle') }}</h3>
        <div class="grid gap-2 mb-6">
            @foreach(__('devdoc.enums.list') as $enum)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-hashtag text-primary-400 me-1"></i> {{ $enum }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.enums.usageTitle') }}</h3>
        <div class="info-box">
            <p class="text-sm">{{ __('devdoc.enums.usageDesc') }}</p>
        </div>
    </section>

    {{-- Section 18: Events & Listeners --}}
    <section id="events" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-bolt text-primary-500 me-2"></i> {{ __('devdoc.events.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.events.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.events.eventsTitle') }}</h3>
        <div class="grid gap-2 mb-6">
            @foreach(__('devdoc.events.eventsList') as $event)
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-broadcast-tower text-blue-400 me-1"></i> {{ $event }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.events.listenersTitle') }}</h3>
        <div class="grid gap-2 mb-6">
            @foreach(__('devdoc.events.listenersList') as $listener)
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-headphones text-green-400 me-1"></i> {{ $listener }}
                </div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.events.howTitle') }}</h3>
        <div class="info-box">
            <p class="text-sm">{{ __('devdoc.events.howDesc') }}</p>
        </div>
    </section>

    {{-- Section 19: Testing --}}
    <section id="testing" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-vial text-primary-500 me-2"></i> {{ __('devdoc.testing.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.testing.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.testing.featureTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-2">{{ __('devdoc.testing.featureDesc') }}</p>
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono leading-relaxed">{{ __('devdoc.testing.featureTests') }}</p>
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.testing.unitTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-2">{{ __('devdoc.testing.unitDesc') }}</p>
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono leading-relaxed">{{ __('devdoc.testing.unitTests') }}</p>
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.testing.browserTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-2">{{ __('devdoc.testing.browserDesc') }}</p>
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
            <p class="text-xs text-gray-500 dark:text-gray-400 font-mono leading-relaxed">{{ __('devdoc.testing.browserTests') }}</p>
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.testing.runTitle') }}</h3>
        <div class="grid gap-2 mb-6">
            @foreach(__('devdoc.testing.runCommands') as $cmd)
                <div class="bg-gray-900 dark:bg-gray-950 rounded-lg p-3 text-sm text-green-400 font-mono">$ {{ $cmd }}</div>
            @endforeach
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.testing.setupTitle') }}</h3>
        <div class="info-box">
            <p class="text-sm">{{ __('devdoc.testing.setupDesc') }}</p>
        </div>
    </section>

    {{-- Section 20: Deployment & Server --}}
    <section id="deployment" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-server text-primary-500 me-2"></i> {{ __('devdoc.deployment.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.deployment.desc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.deployment.cpanelTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.deployment.cpanelDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.deployment.autoDeployTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.deployment.autoDeployDesc') }}</p>

        {{-- cPanel Git Deploy (Primary) --}}
        <h3 class="text-xl font-semibold mb-3"><i class="fas fa-code-branch text-green-500 me-2"></i>{{ __('devdoc.deployment.cpanelGitTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.deployment.cpanelGitDesc') }}</p>

        <div class="grid gap-3 mb-4">
            @foreach(__('devdoc.deployment.cpanelGitRepos') as $key => $repo)
                @php $repoColor = $key === 'production' ? 'red' : 'blue'; @endphp
                <div class="bg-{{ $repoColor }}-50 dark:bg-{{ $repoColor }}-900/20 border border-{{ $repoColor }}-200 dark:border-{{ $repoColor }}-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <span class="bg-{{ $repoColor }}-200 dark:bg-{{ $repoColor }}-800 text-{{ $repoColor }}-800 dark:text-{{ $repoColor }}-200 text-xs font-bold px-2 py-0.5 rounded me-2">{{ strtoupper($key) }}</span>
                    {{ $repo }}
                </div>
            @endforeach
        </div>

        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-5 mb-4">
            <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 space-y-3">
                @foreach(__('devdoc.deployment.cpanelGitSteps') as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </div>

        <div class="warn-box mb-4">
            <p class="text-sm"><i class="fas fa-exclamation-triangle me-1"></i> {{ __('devdoc.deployment.cpanelGitWarning') }}</p>
        </div>

        {{-- Deploy GIF --}}
        <div class="doc-gif-wrap mb-8"><span class="gif-badge">GIF</span><img src="{{ asset('docs/gifs/deploy.gif') }}" alt="{{ __('devdoc.deployment.cpanelGitGifPlaceholder') }}" class="doc-gif"></div>

        {{-- Manual SSH fallback --}}
        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.deployment.stepsTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ __('devdoc.deployment.stepsDesc') }}</p>
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-5 mb-6">
            <ol class="list-decimal list-inside text-sm text-gray-600 dark:text-gray-400 space-y-2">
                @foreach(__('devdoc.deployment.steps') as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.deployment.cronTitle') }}</h3>
        <div class="info-box mb-4">
            <p class="text-sm">{{ __('devdoc.deployment.cronDesc') }}</p>
        </div>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.deployment.queueTitle') }}</h3>
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ __('devdoc.deployment.queueDesc') }}</p>

        <h3 class="text-lg font-semibold mb-3">{{ __('devdoc.deployment.serverInfoTitle') }}</h3>
        <div class="grid gap-3 mb-6">
            @foreach(__('devdoc.deployment.serverInfo') as $info)
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-server text-primary-400 me-1"></i> {{ $info }}
                </div>
            @endforeach
        </div>
    </section>

    {{-- Section 21: Common Gotchas --}}
    <section id="gotchas" class="mb-16 scroll-mt-24">
        <h2 class="text-2xl font-bold mb-6 pb-3 border-b border-gray-200 dark:border-gray-700">
            <i class="fas fa-triangle-exclamation text-primary-500 me-2"></i> {{ __('devdoc.gotchas.title') }}
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">{{ __('devdoc.gotchas.desc') }}</p>

        <div class="space-y-4">
            @foreach(__('devdoc.gotchas.items') as $item)
                <div class="warn-box">
                    <p class="text-sm font-semibold mb-1"><i class="fas fa-exclamation-triangle me-1"></i> {{ $item['title'] }}</p>
                    <p class="text-sm">{{ $item['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

</x-documentation-layout>
