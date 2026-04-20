<x-documentation-layout>
    @push('styles')
        .method-badge {
            font-size: 0.7rem;
            padding: 0.15rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        .http-post {
            background-color: #3B82F6;
            color: white;
        }

        .task-type-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .badge-flight {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-hotel {
            background-color: #fce7f3;
            color: #9f1239;
        }

        .badge-insurance {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-visa {
            background-color: #fef3c7;
            color: #92400e;
        }

        .copy-button {
            position: absolute;
            top: 8px;
            right: 8px;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .copy-button:hover {
            opacity: 1;
        }

        pre, code, .code-block {
            direction: ltr;
            text-align: left;
        }

        pre {
            scrollbar-width: thin;
            scrollbar-color: rgba(156, 163, 175, 0.5) transparent;
        }

        pre::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        pre::-webkit-scrollbar-track {
            background: transparent;
        }

        pre::-webkit-scrollbar-thumb {
            background-color: rgba(156, 163, 175, 0.5);
            border-radius: 4px;
        }
    @endpush

                <div class="prose prose-blue max-w-none dark:prose-invert">
                    {{-- Hero --}}
                    <div class="bg-gradient-to-r from-primary-600 to-blue-600 rounded-xl shadow-lg p-8 mb-12 text-white">
                        <h1 class="text-4xl font-extrabold mb-4">{{ __('apidoc.hero.title') }}</h1>
                        <p class="text-lg opacity-90 max-w-3xl">{{ __('apidoc.hero.desc') }}</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="#endpoint" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-primary-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-primary-700 focus:ring-white transition-colors">
                                {{ __('apidoc.hero.getStarted') }}
                            </a>
                            <a href="#task-types" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-800 bg-opacity-60 hover:bg-opacity-70 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-primary-700 focus:ring-white transition-colors">
                                {{ __('apidoc.hero.viewTaskTypes') }}
                            </a>
                            <a href="{{ route('docs.postman.download') }}" class="inline-flex items-center px-4 py-2 border border-white border-opacity-50 text-sm font-medium rounded-md text-white hover:bg-white hover:bg-opacity-10 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-primary-700 focus:ring-white transition-colors">
                                <i class="fas fa-download me-2"></i>
                                {{ __('apidoc.hero.downloadPostman') }}
                            </a>
                        </div>
                    </div>

                    {{-- Overview --}}
                    <section id="overview" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">{{ __('apidoc.overview.title') }}</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-4">{{ __('apidoc.overview.desc') }}</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div class="bg-blue-50 dark:bg-blue-900/30 border-s-4 border-blue-400 p-4 rounded-md">
                                <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-200 mb-2">{{ __('apidoc.overview.keyFeatures') }}</h3>
                                <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                                    <li>✓ {{ __('apidoc.overview.feature1') }}</li>
                                    <li>✓ {{ __('apidoc.overview.feature2') }}</li>
                                    <li>✓ {{ __('apidoc.overview.feature3') }}</li>
                                    <li>✓ {{ __('apidoc.overview.feature4') }}</li>
                                </ul>
                            </div>
                            <div class="bg-green-50 dark:bg-green-900/30 border-s-4 border-green-400 p-4 rounded-md">
                                <h3 class="text-sm font-semibold text-green-800 dark:text-green-200 mb-2">{{ __('apidoc.overview.supportedTypes') }}</h3>
                                <ul class="text-sm text-green-700 dark:text-green-300 space-y-1">
                                    <li><span class="task-type-badge badge-flight">Flight</span></li>
                                    <li><span class="task-type-badge badge-hotel">Hotel</span></li>
                                    <li><span class="task-type-badge badge-insurance">Insurance</span></li>
                                    <li><span class="task-type-badge badge-visa">Visa</span></li>
                                </ul>
                            </div>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900 border-s-4 border-yellow-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ms-3">
                                    <p class="text-sm text-yellow-700 dark:text-yellow-200">
                                        <strong>{{ __('apidoc.overview.important') }}</strong> {{ __('apidoc.overview.importantDesc') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- API Endpoint --}}
                    <section id="endpoint" class="mb-12">
                        <div class="flex items-center mb-4">
                            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">{{ __('apidoc.endpoint.title') }}</h2>
                            <span class="method-badge http-post ms-3">POST</span>
                        </div>
                        <div class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 border border-gray-200 dark:border-gray-700">
                            <code class="text-sm text-gray-900 dark:text-gray-100">POST /api/task/webhook</code>
                        </div>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-4">{{ __('apidoc.endpoint.desc') }}</p>

                        <h3 class="text-2xl font-semibold mt-8 mb-4 text-gray-900 dark:text-white">{{ __('apidoc.endpoint.headers') }}</h3>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-sm overflow-x-auto border border-gray-200 dark:border-gray-700"><code>Content-Type: application/json
Accept: application/json</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)">
                                <i class="fas fa-copy text-gray-600 dark:text-gray-300"></i>
                            </button>
                        </div>
                    </section>

                    {{-- Common Fields --}}
                    <section id="common-fields" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">{{ __('apidoc.commonFields.title') }}</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">{{ __('apidoc.commonFields.desc') }}</p>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-8">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('apidoc.commonFields.field') }}</th>
                                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('apidoc.commonFields.type') }}</th>
                                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('apidoc.commonFields.required') }}</th>
                                            <th scope="col" class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('apidoc.commonFields.description') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @php
                                            $commonRows = [
                                                ['reference', 'string', true],
                                                ['status', 'string', true],
                                                ['company_id', 'integer', true],
                                                ['type', 'enum', true],
                                                ['supplier_id', 'integer', false],
                                                ['agent_id', 'integer', false],
                                                ['client_name', 'string', false],
                                                ['price', 'decimal', false],
                                                ['tax', 'decimal', false],
                                                ['total', 'decimal', false],
                                                ['issued_date', 'date', false],
                                            ];
                                        @endphp
                                        @foreach($commonRows as $row)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ $row[0] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $row[1] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm {{ $row[2] ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">{{ $row[2] ? __('apidoc.commonFields.yes') : __('apidoc.commonFields.no') }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('apidoc.commonFields.fields.' . $row[0]) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    {{-- Task Types --}}
                    <section id="task-types" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">{{ __('apidoc.taskTypes.title') }}</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">{{ __('apidoc.taskTypes.desc') }}</p>
                    </section>

                    {{-- Flight Task --}}
                    @php $taskTypes = ['flight', 'hotel', 'insurance', 'visa']; @endphp

                    <section id="flight-task" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="task-type-badge badge-flight me-3">FLIGHT</span>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{!! __('apidoc.flight.title') !!}</h3>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">{!! __('apidoc.flight.desc') !!}</p>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ __('apidoc.flight.requiredFields') }} <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_flight_details</code></h4>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.field') }}</th>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.type') }}</th>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.description') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        @php
                                            $flightFields = [
                                                ['departure_time', 'datetime'],
                                                ['arrival_time', 'datetime'],
                                                ['country_id_from', 'integer'],
                                                ['country_id_to', 'integer'],
                                                ['airport_from', 'string'],
                                                ['airport_to', 'string'],
                                                ['airline_id', 'integer'],
                                                ['flight_number', 'string'],
                                                ['ticket_number', 'string'],
                                                ['class_type', 'string'],
                                            ];
                                        @endphp
                                        @foreach($flightFields as $f)
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $f[0] }}</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $f[1] }}</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ __('apidoc.flight.fields.' . $f[0]) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ __('apidoc.flight.exampleRequest') }}</h4>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "reference": "FL-TEST-001",
  "status": "issued",
  "company_id": 1,
  "type": "flight",
  "supplier_id": 2,
  "client_name": "John Doe",
  "agent_id": 1,
  "price": 450.00,
  "tax": 45.00,
  "total": 495.00,
  "exchange_currency": "KWD",
  "issued_date": "2026-01-22",
  "task_flight_details": [
    {
      "is_ancillary": false,
      "farebase": 450.00,
      "departure_time": "2026-02-15 14:00:00",
      "country_id_from": 1,
      "airport_from": "KWI",
      "terminal_from": "T1",
      "arrival_time": "2026-02-15 16:30:00",
      "duration_time": "2h 30m",
      "country_id_to": 2,
      "airport_to": "DXB",
      "terminal_to": "T3",
      "airline_id": 1,
      "flight_number": "KU-671",
      "ticket_number": "3580878589",
      "class_type": "economy",
      "baggage_allowed": "30kg",
      "equipment": "Boeing 777",
      "seat_no": "12A"
    }
  ]
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)"><i class="fas fa-copy text-gray-600 dark:text-gray-300"></i></button>
                        </div>
                    </section>

                    {{-- Hotel Task --}}
                    <section id="hotel-task" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="task-type-badge badge-hotel me-3">HOTEL</span>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{!! __('apidoc.hotel.title') !!}</h3>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">{!! __('apidoc.hotel.desc') !!}</p>

                        <div class="bg-blue-50 dark:bg-blue-900/30 border-s-4 border-blue-400 p-4 rounded-md mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0"><i class="fas fa-info-circle text-blue-400"></i></div>
                                <div class="ms-3">
                                    <p class="text-sm text-blue-700 dark:text-blue-200"><strong>{{ __('apidoc.hotel.note') }}</strong> {!! __('apidoc.hotel.noteDesc') !!}</p>
                                </div>
                            </div>
                        </div>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ __('apidoc.hotel.requiredFields') }} <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_hotel_details</code></h4>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.field') }}</th>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.type') }}</th>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.description') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        @foreach(['hotel_name' => 'string', 'check_in' => 'date', 'check_out' => 'date', 'room_type' => 'string', 'room_amount' => 'integer', 'rate' => 'decimal'] as $field => $type)
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $field }}</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $type }}</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ __('apidoc.hotel.fields.' . $field) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ __('apidoc.hotel.exampleRequest') }}</h4>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "reference": "HT-TEST-001",
  "status": "issued",
  "company_id": 1,
  "type": "hotel",
  "supplier_id": 5,
  "client_name": "Jane Smith",
  "agent_id": 1,
  "price": 300.00,
  "tax": 30.00,
  "total": 330.00,
  "issued_date": "2026-01-22",
  "task_hotel_details": [
    {
      "hotel_name": "Grand Hyatt Hotel",
      "booking_time": "2026-01-22 10:30:00",
      "check_in": "2026-02-20",
      "check_out": "2026-02-23",
      "room_reference": "RM-123456",
      "room_number": "305",
      "room_type": "Deluxe Double Room",
      "room_amount": 1,
      "room_details": "King bed, sea view",
      "rate": 100.00,
      "meal_type": "Breakfast included",
      "is_refundable": true
    }
  ]
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)"><i class="fas fa-copy text-gray-600 dark:text-gray-300"></i></button>
                        </div>
                    </section>

                    {{-- Insurance Task --}}
                    <section id="insurance-task" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="task-type-badge badge-insurance me-3">INSURANCE</span>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{!! __('apidoc.insurance.title') !!}</h3>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">{!! __('apidoc.insurance.desc') !!}</p>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ __('apidoc.insurance.requiredFields') }} <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_insurance_details</code></h4>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.field') }}</th>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.type') }}</th>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.description') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        @foreach(['date' => 'string', 'paid_leaves' => 'integer', 'document_reference' => 'string', 'insurance_type' => 'string', 'destination' => 'string', 'plan_type' => 'string', 'duration' => 'string', 'package' => 'string'] as $field => $type)
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $field }}</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $type }}</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ __('apidoc.insurance.fields.' . $field) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ __('apidoc.insurance.exampleRequest') }}</h4>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "reference": "INS-TEST-001",
  "status": "issued",
  "company_id": 1,
  "type": "insurance",
  "supplier_id": 10,
  "client_name": "Bob Johnson",
  "agent_id": 1,
  "price": 75.00,
  "tax": 7.50,
  "total": 82.50,
  "issued_date": "2026-01-22",
  "task_insurance_details": [
    {
      "date": "2026",
      "paid_leaves": 0,
      "document_reference": "INS-DOC-789456",
      "insurance_type": "Travel Insurance",
      "destination": "Europe",
      "plan_type": "Comprehensive",
      "duration": "15 days",
      "package": "Premium Travel Package"
    }
  ]
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)"><i class="fas fa-copy text-gray-600 dark:text-gray-300"></i></button>
                        </div>
                    </section>

                    {{-- Visa Task --}}
                    <section id="visa-task" class="mb-12">
                        <div class="flex items-center mb-4">
                            <span class="task-type-badge badge-visa me-3">VISA</span>
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">{!! __('apidoc.visa.title') !!}</h3>
                        </div>
                        <p class="text-gray-700 dark:text-gray-300 mb-4">{!! __('apidoc.visa.desc') !!}</p>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ __('apidoc.visa.requiredFields') }} <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded text-sm">task_visa_details</code></h4>
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.field') }}</th>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.type') }}</th>
                                            <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.commonFields.description') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                        @foreach(['visa_type' => 'string', 'application_number' => 'string', 'expiry_date' => 'date', 'number_of_entries' => 'enum', 'stay_duration' => 'integer', 'issuing_country' => 'string'] as $field => $type)
                                        <tr><td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $field }}</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $type }}</td><td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ __('apidoc.visa.fields.' . $field) }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ __('apidoc.visa.exampleRequest') }}</h4>
                        <div class="code-block">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-4 rounded-lg mb-6 text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "reference": "VISA-TEST-001",
  "status": "issued",
  "company_id": 1,
  "type": "visa",
  "supplier_id": 15,
  "client_name": "Alice Brown",
  "agent_id": 1,
  "price": 150.00,
  "tax": 15.00,
  "total": 165.00,
  "issued_date": "2026-01-22",
  "task_visa_details": [
    {
      "visa_type": "Tourist Visa",
      "application_number": "VISA-APP-456789",
      "expiry_date": "2026-08-22",
      "number_of_entries": "double",
      "stay_duration": 90,
      "issuing_country": "United Kingdom"
    }
  ]
}</code></pre>
                            <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)"><i class="fas fa-copy text-gray-600 dark:text-gray-300"></i></button>
                        </div>
                    </section>

                    {{-- Utility Endpoints --}}
                    <section id="utility-endpoints" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">{{ __('apidoc.utility.title') }}</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-6">{{ __('apidoc.utility.desc') }}</p>

                        <div class="bg-blue-50 dark:bg-blue-900/30 border-s-4 border-blue-400 p-4 rounded-md mb-8">
                            <div class="flex">
                                <div class="flex-shrink-0"><i class="fas fa-lightbulb text-blue-400"></i></div>
                                <div class="ms-3">
                                    <p class="text-sm text-blue-700 dark:text-blue-200"><strong>{{ __('apidoc.utility.tip') }}</strong> {{ __('apidoc.utility.tipDesc') }}</p>
                                </div>
                            </div>
                        </div>

                        @php
                            $utilityEndpoints = [
                                ['key' => 'getTaskStructure', 'route' => '/api/get-task-structure', 'code' => "// Request\n{ \"type\": \"flight\" }\n\n// Response\n{\n  \"status\": \"success\",\n  \"data\": {\n    \"fields\": [...],\n    \"detail_fields\": [...]\n  }\n}"],
                                ['key' => 'getClient', 'route' => '/api/get-client', 'code' => "// Request\n{ \"client_id\": 123 }\n\n// Response\n{\n  \"status\": \"success\",\n  \"data\": {\n    \"id\": 123,\n    \"name\": \"John Doe\",\n    \"email\": \"john@example.com\"\n  }\n}"],
                                ['key' => 'getCompany', 'route' => '/api/get-company', 'code' => "// Request\n{ \"company_id\": 1 }\n\n// Response\n{\n  \"status\": \"success\",\n  \"data\": {\n    \"id\": 1,\n    \"name\": \"ACME Travel Agency\"\n  }\n}"],
                                ['key' => 'getAgent', 'route' => '/api/get-agent', 'code' => "// Request\n{ \"agent_id\": 5 }\n\n// Response\n{\n  \"status\": \"success\",\n  \"data\": {\n    \"id\": 5,\n    \"name\": \"Sarah Agent\"\n  }\n}"],
                                ['key' => 'getSupplier', 'route' => '/api/get-supplier', 'code' => "// Request\n{ \"supplier_id\": 2 }\n\n// Response\n{\n  \"status\": \"success\",\n  \"data\": {\n    \"id\": 2,\n    \"name\": \"Amadeus\"\n  }\n}"],
                                ['key' => 'getCountry', 'route' => '/api/get-country', 'code' => "// Request\n{ \"country_id\": 1 }\n\n// Response\n{\n  \"status\": \"success\",\n  \"data\": {\n    \"id\": 1,\n    \"name\": \"Kuwait\",\n    \"code\": \"KW\"\n  }\n}"],
                                ['key' => 'getHotel', 'route' => '/api/get-hotel', 'code' => "// Request\n{ \"hotel_name\": \"Grand Hyatt Hotel\" }\n\n// Response\n{\n  \"status\": \"success\",\n  \"data\": {\n    \"id\": 42,\n    \"name\": \"Grand Hyatt Hotel\",\n    \"city\": \"Dubai\"\n  }\n}"],
                            ];
                        @endphp

                        <div class="space-y-6">
                            @foreach($utilityEndpoints as $ep)
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('apidoc.utility.' . $ep['key']) }}</h3>
                                        <span class="method-badge http-post">POST</span>
                                    </div>
                                    <code class="text-sm text-gray-600 dark:text-gray-400">{{ $ep['route'] }}</code>
                                </div>
                                <div class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ __('apidoc.utility.' . $ep['key'] . 'Desc') }}</p>
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{{ $ep['code'] }}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)"><i class="fas fa-copy text-gray-600 dark:text-gray-300"></i></button>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- Responses --}}
                    <section id="responses" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">{{ __('apidoc.responses.title') }}</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white flex items-center">
                                        <span class="h-6 w-6 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center me-2">
                                            <i class="fas fa-check text-green-600 dark:text-green-400 text-sm"></i>
                                        </span>
                                        {{ __('apidoc.responses.success') }}
                                    </h3>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "status": "success",
  "message": "Task created successfully via webhook",
  "data": {
    "task_id": 12345,
    "reference": "FL-TEST-001",
    "type": "flight",
    "enabled": true
  }
}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)"><i class="fas fa-copy text-gray-600 dark:text-gray-300"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-5 border-b border-gray-200 dark:border-gray-700 sm:px-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white flex items-center">
                                        <span class="h-6 w-6 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center me-2">
                                            <i class="fas fa-times text-red-600 dark:text-red-400 text-sm"></i>
                                        </span>
                                        {{ __('apidoc.responses.validationError') }}
                                    </h3>
                                </div>
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="code-block">
                                        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-xs overflow-x-auto border border-gray-200 dark:border-gray-700"><code>{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "reference": ["The reference field is required."],
    "company_id": ["The company id field is required."]
  }
}</code></pre>
                                        <button class="copy-button p-2 bg-gray-200 dark:bg-gray-700 rounded-md" onclick="copyCode(this)"><i class="fas fa-copy text-gray-600 dark:text-gray-300"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    {{-- Error Handling --}}
                    <section id="error-handling" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">{{ __('apidoc.errorHandling.title') }}</h2>
                        <p class="text-lg text-gray-700 dark:text-gray-300 mb-4">{{ __('apidoc.errorHandling.desc') }}</p>

                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 mb-8">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.errorHandling.statusCode') }}</th>
                                            <th class="px-6 py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ __('apidoc.errorHandling.statusDesc') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600 dark:text-green-400">201 Created</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('apidoc.errorHandling.codes.201') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600 dark:text-red-400">422 Unprocessable Entity</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('apidoc.errorHandling.codes.422') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600 dark:text-red-400">500 Internal Server Error</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ __('apidoc.errorHandling.codes.500') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-3">{{ __('apidoc.errorHandling.commonErrors') }}</h3>
                        <div class="space-y-3">
                            <div class="bg-red-50 dark:bg-red-900/20 border-s-4 border-red-400 p-4 rounded-md">
                                <p class="text-sm text-red-700 dark:text-red-300 font-medium">{{ __('apidoc.errorHandling.error1Title') }}</p>
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ __('apidoc.errorHandling.error1Desc') }}</p>
                            </div>
                            <div class="bg-red-50 dark:bg-red-900/20 border-s-4 border-red-400 p-4 rounded-md">
                                <p class="text-sm text-red-700 dark:text-red-300 font-medium">{{ __('apidoc.errorHandling.error2Title') }}</p>
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ __('apidoc.errorHandling.error2Desc') }}</p>
                            </div>
                            <div class="bg-red-50 dark:bg-red-900/20 border-s-4 border-red-400 p-4 rounded-md">
                                <p class="text-sm text-red-700 dark:text-red-300 font-medium">{{ __('apidoc.errorHandling.error3Title') }}</p>
                                <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ __('apidoc.errorHandling.error3Desc') }}</p>
                            </div>
                        </div>
                    </section>

                    {{-- Best Practices --}}
                    <section id="best-practices" class="mb-12">
                        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">{{ __('apidoc.bestPractices.title') }}</h2>
                        <div class="space-y-6">
                            @php
                                $practices = [
                                    ['key' => 'uniqueRef', 'icon' => 'fas fa-check-circle text-green-500'],
                                    ['key' => 'validateFk', 'icon' => 'fas fa-database text-blue-500'],
                                    ['key' => 'dateFormat', 'icon' => 'fas fa-calendar-check text-purple-500'],
                                    ['key' => 'errorGraceful', 'icon' => 'fas fa-exclamation-triangle text-yellow-500'],
                                    ['key' => 'monitorLogs', 'icon' => 'fas fa-file-alt text-indigo-500'],
                                ];
                            @endphp
                            @foreach($practices as $p)
                            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-5 sm:p-6">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-2">
                                        <i class="{{ $p['icon'] }} me-2"></i>
                                        {{ __('apidoc.bestPractices.' . $p['key']) }}
                                    </h3>
                                    <p class="text-gray-500 dark:text-gray-400">{{ __('apidoc.bestPractices.' . $p['key'] . 'Desc') }}</p>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </section>

                    {{-- Footer --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-8 mt-12">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ __('apidoc.footer.needHelp') }}</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('apidoc.footer.needHelpDesc') }}</p>
                            </div>
                            <div class="mt-4 md:mt-0">
                                <a href="#" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    {{ __('apidoc.footer.contactSupport') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

    @push('scripts')
    <script>
        function copyCode(button) {
            const pre = button.parentElement.querySelector('pre');
            const code = pre.textContent;

            navigator.clipboard.writeText(code).then(() => {
                const originalHTML = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check text-green-600 dark:text-green-400"></i>';

                setTimeout(() => {
                    button.innerHTML = originalHTML;
                }, 2000);
            });
        }
    </script>
    @endpush
</x-documentation-layout>
