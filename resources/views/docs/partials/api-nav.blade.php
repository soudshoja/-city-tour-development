    const groups = [
        {
            label: @json(__('apidoc.nav.apiReference')),
            links: [
                { id: 'overview', title: @json(__('apidoc.nav.link.overview')), icon: 'fas fa-info-circle' },
                { id: 'endpoint', title: @json(__('apidoc.nav.link.endpoint')), icon: 'fas fa-code' },
                { id: 'task-types', title: @json(__('apidoc.nav.link.taskTypes')), icon: 'fas fa-layer-group' },
            ]
        },
        {
            label: @json(__('apidoc.nav.taskDetails')),
            links: [
                { id: 'flight-task', title: @json(__('apidoc.nav.link.flightTask')), icon: 'fas fa-plane' },
                { id: 'hotel-task', title: @json(__('apidoc.nav.link.hotelTask')), icon: 'fas fa-hotel' },
                { id: 'insurance-task', title: @json(__('apidoc.nav.link.insuranceTask')), icon: 'fas fa-shield-halved' },
                { id: 'visa-task', title: @json(__('apidoc.nav.link.visaTask')), icon: 'fas fa-passport' },
            ]
        },
        {
            label: @json(__('apidoc.nav.reference')),
            links: [
                { id: 'utility-endpoints', title: @json(__('apidoc.nav.link.utilityEndpoints')), icon: 'fas fa-tools' },
                { id: 'responses', title: @json(__('apidoc.nav.link.responses')), icon: 'fas fa-reply' },
                { id: 'error-handling', title: @json(__('apidoc.nav.link.errorHandling')), icon: 'fas fa-exclamation-triangle' },
                { id: 'best-practices', title: @json(__('apidoc.nav.link.bestPractices')), icon: 'fas fa-check-circle' },
            ]
        },
    ];

    const sections = [
        { id: 'overview', title: @json(__('apidoc.nav.link.overview')), keywords: 'overview introduction api' },
        { id: 'endpoint', title: @json(__('apidoc.nav.link.endpoint')), keywords: 'endpoint url post webhook' },
        { id: 'task-types', title: @json(__('apidoc.nav.link.taskTypes')), keywords: 'task type flight hotel visa insurance' },
        { id: 'flight-task', title: @json(__('apidoc.nav.link.flightTask')), keywords: 'flight airline pnr booking' },
        { id: 'hotel-task', title: @json(__('apidoc.nav.link.hotelTask')), keywords: 'hotel room booking accommodation' },
        { id: 'insurance-task', title: @json(__('apidoc.nav.link.insuranceTask')), keywords: 'insurance policy coverage' },
        { id: 'visa-task', title: @json(__('apidoc.nav.link.visaTask')), keywords: 'visa passport application' },
        { id: 'utility-endpoints', title: @json(__('apidoc.nav.link.utilityEndpoints')), keywords: 'utility helper get client company' },
        { id: 'responses', title: @json(__('apidoc.nav.link.responses')), keywords: 'response success error json' },
        { id: 'error-handling', title: @json(__('apidoc.nav.link.errorHandling')), keywords: 'error validation 422 401' },
        { id: 'best-practices', title: @json(__('apidoc.nav.link.bestPractices')), keywords: 'best practice recommendation tip' },
    ];
