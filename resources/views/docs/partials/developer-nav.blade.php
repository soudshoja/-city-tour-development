    const groups = [
        {
            label: @json(__('devdoc.nav.foundation')),
            links: [
                { id: 'tech-stack', title: @json(__('devdoc.nav.link.techStack')), icon: 'fas fa-layer-group' },
                { id: 'database', title: @json(__('devdoc.nav.link.database')), icon: 'fas fa-database' },
                { id: 'roles', title: @json(__('devdoc.nav.link.roles')), icon: 'fas fa-shield-halved' },
                { id: 'auth', title: @json(__('devdoc.nav.link.auth')), icon: 'fas fa-lock' },
                { id: 'business-flows', title: @json(__('devdoc.nav.link.businessFlows')), icon: 'fas fa-diagram-project' },
            ]
        },
        {
            label: @json(__('devdoc.nav.core')),
            links: [
                { id: 'services', title: @json(__('devdoc.nav.link.services')), icon: 'fas fa-cubes' },
                { id: 'commands', title: @json(__('devdoc.nav.link.commands')), icon: 'fas fa-terminal' },
                { id: 'jobs', title: @json(__('devdoc.nav.link.jobs')), icon: 'fas fa-clock-rotate-left' },
                { id: 'payments', title: @json(__('devdoc.nav.link.payments')), icon: 'fas fa-credit-card' },
            ]
        },
        {
            label: @json(__('devdoc.nav.integrations')),
            links: [
                { id: 'graphql', title: @json(__('devdoc.nav.link.graphql')), icon: 'fas fa-circle-nodes' },
                { id: 'email', title: @json(__('devdoc.nav.link.email')), icon: 'fas fa-envelope' },
                { id: 'file-processing', title: @json(__('devdoc.nav.link.fileProcessing')), icon: 'fas fa-file-pdf' },
                { id: 'ai', title: @json(__('devdoc.nav.link.ai')), icon: 'fas fa-robot' },
            ]
        },
        {
            label: @json(__('devdoc.nav.development')),
            links: [
                { id: 'frontend', title: @json(__('devdoc.nav.link.frontend')), icon: 'fas fa-palette' },
                { id: 'localization', title: @json(__('devdoc.nav.link.localization')), icon: 'fas fa-language' },
                { id: 'enums', title: @json(__('devdoc.nav.link.enums')), icon: 'fas fa-list-ol' },
                { id: 'events', title: @json(__('devdoc.nav.link.events')), icon: 'fas fa-bolt' },
                { id: 'testing', title: @json(__('devdoc.nav.link.testing')), icon: 'fas fa-vial' },
                { id: 'deployment', title: @json(__('devdoc.nav.link.deployment')), icon: 'fas fa-server' },
                { id: 'gotchas', title: @json(__('devdoc.nav.link.gotchas')), icon: 'fas fa-triangle-exclamation' },
            ]
        },
    ];

    const sections = [
        { id: 'tech-stack', title: @json(__('devdoc.nav.link.techStack')), keywords: 'php laravel setup install composer npm' },
        { id: 'database', title: @json(__('devdoc.nav.link.database')), keywords: 'model migration eloquent mysql table' },
        { id: 'roles', title: @json(__('devdoc.nav.link.roles')), keywords: 'role permission spatie policy admin company agent' },
        { id: 'auth', title: @json(__('devdoc.nav.link.auth')), keywords: 'login authentication security 2fa password' },
        { id: 'business-flows', title: @json(__('devdoc.nav.link.businessFlows')), keywords: 'task invoice payment refund booking flow' },
        { id: 'services', title: @json(__('devdoc.nav.link.services')), keywords: 'service layer charge payment gateway' },
        { id: 'commands', title: @json(__('devdoc.nav.link.commands')), keywords: 'artisan command console schedule cron' },
        { id: 'jobs', title: @json(__('devdoc.nav.link.jobs')), keywords: 'job queue worker background async' },
        { id: 'payments', title: @json(__('devdoc.nav.link.payments')), keywords: 'payment gateway tap myfatoorah hesabe upayment knet' },
        { id: 'graphql', title: @json(__('devdoc.nav.link.graphql')), keywords: 'graphql api lighthouse schema query mutation' },
        { id: 'email', title: @json(__('devdoc.nav.link.email')), keywords: 'email mail imap smtp notification' },
        { id: 'file-processing', title: @json(__('devdoc.nav.link.fileProcessing')), keywords: 'pdf air file parse ocr excel' },
        { id: 'ai', title: @json(__('devdoc.nav.link.ai')), keywords: 'ai openai gpt embedding' },
        { id: 'frontend', title: @json(__('devdoc.nav.link.frontend')), keywords: 'blade component livewire layout alpine tailwind' },
        { id: 'localization', title: @json(__('devdoc.nav.link.localization')), keywords: 'locale language translation arabic english rtl' },
        { id: 'enums', title: @json(__('devdoc.nav.link.enums')), keywords: 'enum type status constant' },
        { id: 'events', title: @json(__('devdoc.nav.link.events')), keywords: 'event listener dispatch' },
        { id: 'testing', title: @json(__('devdoc.nav.link.testing')), keywords: 'test phpunit dusk feature unit browser' },
        { id: 'deployment', title: @json(__('devdoc.nav.link.deployment')), keywords: 'deploy server cpanel cron production' },
        { id: 'gotchas', title: @json(__('devdoc.nav.link.gotchas')), keywords: 'gotcha warning issue bug known' },
    ];
