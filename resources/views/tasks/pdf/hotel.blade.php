<!DOCTYPE html>
<html lang="en" class="antialiased print:bg-white">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Hotel Voucher: {{ $tasks->first()->reference }}</title>
    <link rel="icon" href="{{ asset('images/City0logo.svg') }}" type="image/x-icon" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        rel="stylesheet" />
    <style>
        @media print {
            .page-break-inside-avoid {
                page-break-inside: avoid;
            }

            *,
            ::before,
            ::after {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .print\\:hidden {
                display: none !important;
            }

            @page {
                margin: 0;
            }

            body {
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-900 font-sans p-6 flex justify-center">
    <div class="w-full max-w-3xl bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="bg-blue-800 text-white px-8 py-6 flex justify-between items-center border-b-4 border-yellow-500">
            <div class="flex items-center space-x-4">
                <img src="{{ asset('images/City0logo.svg') }}"
                    alt="City Travelers" class="h-12 w-12 object-contain" />
                <div>
                    <h1 class="text-xl font-bold">{{ $tasks->first()->company->name }}</h1>
                    <p class="text-sm opacity-75">Your Trusted Travel Partner</p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-3xl font-extrabold tracking-wider">{{ $tasks->first()->reference }}</div>
                <div class="text-sm uppercase opacity-75 mt-1">Hotel Voucher</div>
            </div>
        </div>
        <div class="p-8 space-y-8">
            <section class="page-break-inside-avoid">
                <h2 class="flex items-center text-blue-800 text-lg font-semibold mb-4">
                    <i class="fas fa-user-tie mr-2"></i>Agent Information
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @php $agent = $tasks->first()->agent; @endphp
                    <div>
                        <div class="text-xs uppercase text-gray-500">Name</div>
                        <div class="text-sm font-medium text-gray-900">{{ $agent->name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Email</div>
                        <div class="text-sm font-medium text-gray-900">
                            @if($agent->email)
                            <a href="mailto:{{ $agent->email }}" class="text-blue-600 hover:underline">
                                {{ $agent->email }}
                            </a>
                            @else — @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Phone</div>
                        <div class="text-sm font-medium text-gray-900">{{ $agent->phone_number ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Issued Date</div>
                        <div class="text-sm font-medium text-gray-900">
                            {{ optional($tasks->first()->issued_date)->format('d M Y') ?? '—' }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="page-break-inside-avoid">
                <h2 class="flex items-center text-blue-800 text-lg font-semibold mb-4">
                    <i class="fas fa-id-card mr-2"></i>Client Information
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    @php $client = $tasks->first()->client; @endphp
                    <div>
                        <div class="text-xs uppercase text-gray-500">Name</div>
                        <div class="text-sm font-medium text-gray-900">{{ $client->first_name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Email</div>
                        <div class="text-sm font-medium text-gray-900">
                            @if($client->email)
                            <a href="mailto:{{ $client->email }}" class="text-blue-600 hover:underline">
                                {{ $client->email }}
                            </a>
                            @else — @endif
                        </div>
                    </div>
                </div>
            </section>
            <section class="page-break-inside-avoid">
                <h2 class="flex items-center text-blue-800 text-lg font-semibold mb-4">
                    <i class="fas fa-calendar-alt mr-2"></i>Stay Timeline
                </h2>
                <div class="relative border-l-2 border-gray-200 ml-6">
                    @foreach($hotelDetails as $i => $d)
                    <div class="mb-8 pl-6 relative">
                        <span class="absolute -left-3 top-1 bg-blue-800 text-white w-6 h-6 rounded-full
                           flex items-center justify-center text-sm">
                            {{ $i + 1 }}
                        </span>
                        <div class="bg-white shadow-sm rounded-lg p-4 grid grid-cols-1 sm:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div class="uppercase text-xs text-gray-500">Check-In</div>
                                <div class="font-medium text-gray-900">{{ $d->check_in ? \Carbon\Carbon::parse($d->check_in)->format('d M Y') : '—' }}</div>
                            </div>
                            <div>
                                <div class="uppercase text-xs text-gray-500">Check-Out</div>
                                <div class="font-medium text-gray-900">{{ $d->check_out ? \Carbon\Carbon::parse($d->check_out)->format('d M Y') : '—' }}</div>
                            </div>
                            <div>
                                <div class="uppercase text-xs text-gray-500">Booked On</div>
                                <div class="font-medium text-gray-900">
                                    {{ $d->booking_time ? \Carbon\Carbon::parse($d->booking_time)->format('d M Y, H:i') : '—' }}
                                </div>
                            </div>
                            <div>
                                <div class="uppercase text-xs text-gray-500">Nights</div>
                                <div class="font-medium text-gray-900">{{ $d->nights . ' days' ?? '—' }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </section>
            <section class="page-break-inside-avoid">
                <h2 class="flex items-center text-blue-800 text-lg font-semibold mb-4">
                    <i class="fas fa-bed mr-2"></i>
                    Room Details
                </h2>
                @php
                    $boardLabels = [
                    'RO' => 'Room Only',
                    'SC' => 'Self-Catering',
                    'BB' => 'Bed & Breakfast',
                    'HB' => 'Half Board',
                    'FB' => 'Full Board',
                    'AI' => 'All Inclusive',
                    'RD' => 'Room Description',
                    ];
                @endphp
                @foreach($hotelDetails as $d)
                    @php
                        $rd         = json_decode($d->room_details, true) ?: [];
                        $name       = $rd['name']            ?? $d->room_type    ?? 'Standard Room';
                        $code       = strtoupper($rd['boardBasis'] ?? $rd['board'] ?? 'RO');
                        $board      = $boardLabels[$code]    ?? $code;
                        $refNo      = $d->room_number        ?? 'TBD';
                        $info       = $rd['info']            ?? '';
                        $supps      = $d->supplements        ?? null;
                        $extras     = $rd['extraServices']   ?? [];
                        $guestIds   = $rd['passengers']      ?? [];
                        $refundable = $d->is_refundable      ?? false;
                    @endphp
                    <div class="bg-white border border-gray-200 rounded-lg shadow mb-6 overflow-hidden">
                        <div class="px-6 py-4 flex flex-col md:flex-row md:justify-between md:items-center">
                            <h3 class="text-xl font-semibold text-gray-900">{{ $name }}</h3>
                            <div class="mt-3 md:mt-0 flex flex-wrap gap-2">
                            <span class="inline-block bg-blue-800 text-white text-xs font-medium px-3 py-1 rounded-full">
                                {{ $board }}
                            </span>
                            <span class="inline-block text-xs font-medium px-3 py-1 rounded-full {{ $refundable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $refundable ? 'Refundable' : 'Non-refundable' }}
                            </span>
                            </div>
                        </div>
                        <div class="bg-gray-50 px-6 py-4 space-y-4 border-t border-gray-200 text-sm">
                            @if($info)
                            <div>
                                <div class="uppercase text-xs text-gray-500 mb-1">Details</div>
                                <div class="text-gray-800 leading-relaxed">{!! nl2br(e($info)) !!}</div>
                            </div>
                            @endif
                            @if(count($extras))
                            <div>
                                <div class="uppercase text-xs text-gray-500 mb-1">Extra Services</div>
                                <ul class="list-disc pl-5 space-y-1 text-gray-800">
                                @foreach($extras as $svc)
                                    <li>{{ $svc }}</li>
                                @endforeach
                                </ul>
                            </div>
                            @endif
                            @if(!empty($guestIds))
                            <div>
                                <div class="uppercase text-xs text-gray-500 mb-2 flex items-center">
                                    <i class="fas fa-users mr-1"></i> Guests on this Booking
                                </div>
                                <div class="grid grid-cols-3 gap-1 text-sm text-gray-800">
                                @foreach($guestIds as $pid)
                                    <div class="flex items-center">
                                        <i class="fas fa-user-circle mr-2 text-gray-500"></i>
                                        Guest #{{ $pid }}
                                    </div>
                                @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </section>
            <section class="page-break-inside-avoid">
                <h2 class="flex items-center text-blue-800 text-lg font-semibold mb-4">
                    <i class="fas fa-users mr-2"></i>Guests
                </h2>
                <div class="space-y-4">
                    @foreach($tasks as $t)
                    @php
                        $status = strtolower($t->status ?? 'booked');
                        $map = [
                        'confirmed' => ['border' => 'green-500','bg' => 'green-100','text' => 'green-800','icon'=>'green-500'],
                        'booked'    => ['border' => 'blue-500', 'bg' => 'blue-100', 'text'=>'blue-800', 'icon'=>'blue-500'],
                        'canceled'  => ['border' => 'red-500',  'bg' => 'red-100',  'text'=>'red-800',  'icon'=>'red-500'],
                        ];
                        $c = $map[$status] ?? $map['booked'];
                    @endphp
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4
                        flex justify-between items-center">
                        <div class="font-medium text-gray-900">{{ $t->passenger_name ?? $t->client->first_name }}</div>
                        <span class="uppercase text-xs font-semibold px-2 py-1 rounded {{ "bg-{$c['bg']}" }} {{ "text-{$c['text']}" }}">
                            {{ ucfirst($status) }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </section>
            @php
                $raw      = $tasks->first()->cancellation_policy;
                $policies = [];
                if ($raw) {
                    $decoded = @json_decode($raw, true);
                    if (is_string($decoded)) {
                        $decoded = @json_decode($decoded, true);
                    }
                    if (is_array($decoded)) {
                        $policies = $decoded;
                    }
                }
            @endphp
            @if(count($policies))
            <section class="page-break-inside-avoid">
                <div class="bg-yellow-50 border-l-4 border-yellow-500 rounded-lg p-4">
                    <div class="flex items-center mb-3 text-yellow-800 font-semibold">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span>Cancellation Policy</span>
                    </div>
                    @foreach($policies as $p)
                    <div class="grid grid-cols-2 gap-x-6 gap-y-2 mb-4">
                        @foreach($p as $field => $value)
                        <div class="flex items-center text-sm text-yellow-900">
                            <i class="fas fa-dot-circle mr-2 text-yellow-600 text-xs"></i>
                            <span class="font-medium">{{ ucwords(str_replace('_',' ',$field)) }}:</span>
                            <span class="ml-1">{{ $value }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            </section>
            @endif
        </div>
        <div class="bg-gray-800 text-white text-center py-4 flex justify-between items-center px-8 print:hidden">
            <div class="text-sm opacity-75">
                © {{ date('Y') }} City Travelers. Voucher valid for the specified booking only.
            </div>
            <button onclick="window.print()"
                class="bg-yellow-500 hover:bg-yellow-600 px-4 py-2 rounded inline-flex items-center">
                <i class="fas fa-download mr-2"></i>Download PDF
            </button>
        </div>
    </div>
</body>

</html>