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
                margin: 12mm 10mm;
            }

            body {
                margin: 0;
                padding: 0;
            }
            .page-break-inside-avoid,
            .card,
            .card-inner {
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .print-break-before-page { break-before: page; }
            .print-top-pad { padding-top: 2mm; }
        }
        :root {
            --brand-900: #1e3a8a;
            --brand-700: #2563eb;
            --brand-500: #3b82f6;
            --brand-300: #60a5fa;
            --accent: #f59e0b;
            --card-bg: #f8fafc;
            --ring: #e5e7eb;
        }

        body {
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
        }
        .header-divider {
            box-shadow: inset 0 -4px 0 0 var(--accent);
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--ring);
            border-radius: 0.75rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
        }
        .card-inner {
            background: white;
            border: 1px solid var(--ring);
            border-radius: 0.75rem;
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-900 font-sans p-6 flex justify-center">
    <div class="w-full max-w-3xl bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-[var(--brand-700)] to-[var(--brand-500)] text-white px-8 py-6 header-divider">
            <div class="flex flex-col md:flex-row print:flex-row md:items-center md:justify-between print:items-center print:justify-between gap-4 print:gap-4">
                <div class="flex items-center gap-4 flex-1 print:flex-1">
                    <img class="w-auto h-[65px] object-contain" src="{{ $company->logo ? Storage::url($company->logo) : asset('images/UserPic.svg') }}" alt="Company logo" />
                    <div>
                        <h1 class="text-2xl font-bold">{{ $company?->name ?? 'Company' }}</h1>
                        <p class="text-base opacity-90">{{ $company?->tagline ?? 'Your Trusted Travel Partner' }}</p>
                        <div class="text-sm mt-2 opacity-90 space-x-3">
                            @if($company?->address)<span><i class="fa-solid fa-location-dot mr-1"></i>{{ $company->address }}</span>@endif
                            @if($company?->phone)<span><i class="fa-solid fa-phone mr-1"></i>{{ $company->phone }}</span>@endif
                            @if($company?->email)<span><i class="fa-solid fa-envelope mr-1"></i>{{ $company->email }}</span>@endif
                            @if($company?->website)<span><i class="fa-solid fa-globe mr-1"></i>{{ $company->website }}</span>@endif
                        </div>
                        @php
                            $socials = [
                                'facebook' => ['url' => $company?->facebook, 'icon' => 'fa-facebook-f', 'label' => 'Facebook'],
                                'instagram' => ['url' => $company?->instagram, 'icon' => 'fa-instagram', 'label' => 'Instagram'],
                                'snapchat' => ['url' => $company?->snapchat, 'icon' => 'fa-snapchat-ghost', 'label' => 'Snapchat'],
                                'whatsapp' => ['url' => $company?->whatsapp, 'icon' => 'fa-whatsapp', 'label' => 'Whatsapp'],
                                'tiktok' => ['url' => $company?->tiktok, 'icon' => 'fa-tiktok', 'label' => 'TikTok'],
                            ];
                        @endphp
                        <div class="flex items-center gap-2 mt-3">
                            @foreach($socials as $social)
                                @if(!empty($social['url']))
                                    <a href="{{ $social['url'] }}" target="_blank" 
                                    class="group w-8 h-8 flex items-center justify-center rounded-full bg-blue-800 hover:bg-gray-300 transition-all duration-200"
                                    title="{{ $social['label'] }}">
                                        <i class="fa-brands {{ $social['icon'] }} text-white group-hover:text-[var(--brand-700)]"></i>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-extrabold tracking-wider">{{ $tasks->first()->reference }}</div>
                    <div class="mt-1">
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white/15 ring-1 ring-white/30 text-xs uppercase tracking-wide">
                            <i class="fa-solid fa-ticket"></i> Hotel Voucher
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <section class="page-break-inside-avoid card p-5">
                <h2 class="flex items-center text-[var(--brand-900)] text-lg font-semibold mb-4">
                    <i class="fas fa-id-card mr-2"></i>Client Information
                </h2>
                <div class="card-inner p-5 grid grid-cols-1 sm:grid-cols-[1.5fr_1.5fr_1fr] gap-5">
                    @php $client = $tasks->first()->client; @endphp
                    <div>
                        <div class="text-xs uppercase font-semibold text-gray-500">Name</div>
                        <div class="text-sm font-medium text-gray-900">{{ $client->full_name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase font-semibold text-gray-500">Email</div>
                        <div class="text-sm font-medium text-gray-900">
                            @if($client->email)
                            <a href="mailto:{{ $client->email }}" class="text-blue-700 hover:underline">
                                {{ $client->email }}
                            </a>
                            @else — @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase font-semibold text-gray-500">Phone</div>
                        <div class="text-sm font-medium text-gray-900">{{ ($client->country_code ?? '') . ($client->phone ?? '') ?: '—' }}</div>
                    </div>
                </div>
            </section>

            <section class="page-break-inside-avoid card p-5">
                <h2 class="flex items-center text-[var(--brand-900)] text-lg font-semibold mb-4">
                    <i class="fas fa-calendar-alt mr-2"></i>Stay Timeline
                </h2>
                @php $hasBooked = !empty($hotelDetail?->booking_time); @endphp
                <div @class(['card-inner p-5 grid grid-cols-1 gap-4 text-sm',
                    'sm:grid-cols-[1.5fr_1fr_1fr_1fr_1fr]' => $hasBooked, 'sm:grid-cols-[1.5fr_1fr_1fr_1fr]' => !$hasBooked])>
                    <div>
                        <div class="uppercase text-xs font-semibold text-gray-500">Hotel</div>
                        <div class="font-medium text-gray-900">{{ $hotelDetail->hotel?->name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="uppercase text-xs font-semibold text-gray-500">Check-In</div>
                        <div class="font-medium text-gray-900">{{ $hotelDetail?->check_in ? \Carbon\Carbon::parse($hotelDetail->check_in)->format('d M Y') : '—' }}</div>
                    </div>
                    <div>
                        <div class="uppercase text-xs font-semibold text-gray-500">Check-Out</div>
                        <div class="font-medium text-gray-900">{{ $hotelDetail?->check_out ? \Carbon\Carbon::parse($hotelDetail->check_out)->format('d M Y') : '—' }}</div>
                    </div>
                    @if($hotelDetail?->booking_time)
                    <div>
                        <div class="uppercase text-xs font-semibold text-gray-500">Booked On</div>
                        <div class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($hotelDetail->booking_time)->format('d M Y, H:i') }}</div>
                    </div>
                    @endif
                    <div>
                        <div class="uppercase text-xs font-semibold text-gray-500">Nights</div>
                        <div class="font-medium text-gray-900">{{ $hotelDetail?->nights ? $hotelDetail->nights.' days' : '—' }}</div>
                    </div>
                </div>
            </section>

            <section class="page-break-inside-avoid card p-5">
                <h2 class="flex items-center text-[var(--brand-900)] text-lg font-semibold mb-4">
                    <i class="fas fa-user-tie mr-2"></i>Agent Information
                </h2>
                <div class="card-inner p-5 grid grid-cols-1 sm:grid-cols-3 gap-6">
                    @php $agent = $tasks->first()->agent; @endphp
                    <div>
                        <div class="text-xs uppercase font-semibold text-gray-500">Name</div>
                        <div class="text-sm font-medium text-gray-900">{{ $agent->name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase font-semibold text-gray-500">Email</div>
                        <div class="text-sm font-medium text-gray-900">
                            @if($agent->email)
                            <a href="mailto:{{ $agent->email }}" class="text-blue-600 hover:underline">
                                {{ $agent->email }}
                            </a>
                            @else — @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase font-semibold text-gray-500">Phone</div>
                        <div class="text-sm font-medium text-gray-900">{{ $agent->phone_number ?? '—' }}</div>
                    </div>
                </div>
            </section>

            <div class="print-break-before-page print-top-pad">
                <section class="page-break-inside-avoid card p-5">
                    <h2 class="flex items-center text-[var(--brand-900)] text-lg font-semibold mb-4">
                        <i class="fas fa-bed mr-2"></i>Room Details
                    </h2>
                    @php
                        $rd = json_decode($hotelDetail?->room_details ?? '[]', true) ?: [];
                        $name = $rd['name'] ?? $hotelDetail?->room_type ?? null;
                        $code = strtoupper($rd['boardBasis'] ?? $rd['board'] ?? 'RO');
                        $board = $boardLabels[$code] ?? $code;
                        $info = $rd['info'] ?? '';
                        $extras = $rd['extraServices'] ?? [];
                        $guestIds = $rd['passengers'] ?? [];
                    @endphp
                    <div class="card-inner">
                        <div class="px-5 py-3 flex flex-col md:flex-row md:justify-between md:items-center">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $name }}</h3>
                            <div class="mt-3 md:mt-0 flex flex-wrap gap-2">
                                <span class="inline-block bg-[var(--brand-900)] text-white text-xs font-medium px-3 py-1 rounded-full">{{ $board }}</span>
                                @if(!is_null($hotelDetail?->is_refundable))
                                <span class="inline-block text-xs font-medium px-3 py-1 rounded-full
                                    {{ $hotelDetail?->is_refundable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $hotelDetail?->is_refundable ? 'Refundable' : 'Non-refundable' }}
                                </span>
                                @endif
                                @if($tasks->first()?->supplier_pay_date)
                                <span class="inline-flex items-center text-xs font-medium px-3 py-1 rounded-full bg-sky-100 text-sky-900 border border-sky-300">
                                    <i class="fa-regular fa-calendar mr-1 text-sky-500"></i> Issued: {{ $tasks->first()?->supplier_pay_date ? \Carbon\Carbon::parse($tasks->first()->supplier_pay_date)->format('d M Y') : null }}
                                </span>
                                @endif
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
                                <div class="uppercase text-xs font-semibold text-gray-600 mb-2 flex items-center">
                                    <i class="fas fa-users mr-1 text-[var(--brand-900)]"></i> Guests on this Booking
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($guestIds as $pid)
                                    <div class="flex items-center bg-white border border-gray-200 rounded-full px-3 py-1.5 text-sm text-gray-800 shadow-sm hover:shadow-md transition-all duration-200">
                                        <i class="fas fa-user mr-2 text-[var(--brand-900)]"></i>
                                        <span>{{ $pid }}</span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </section>
            </div>

            @if($tasks->count() > 1)
            <section class="page-break-inside-avoid card p-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="flex items-center text-[var(--brand-900)] text-lg font-semibold">
                        <i class="fas fa-users mr-2"></i> Related Booking Passengers
                    </h2>
                    @if($tasks->first()?->reference)
                    <span class="inline-flex items-center text-xs font-medium px-3 py-1 rounded-full bg-cyan-100 text-cyan-800 border border-cyan-300">
                        <i class="fa-solid fa-link mr-1 text-cyan-500"></i>
                        Ref: {{ $tasks->first()->reference }}
                    </span>
                    @endif
                </div>
                <div class="space-y-2">
                @foreach($tasks as $t)
                    @continue($t->id === $tasks->first()->id)
                    @php
                    $status = strtolower($t->status ?? 'issued');
                    $map = [
                        'issued' => ['bg' => 'green-100', 'text' => 'green-800'],
                        'confirmed' => ['bg' => 'blue-100', 'text'=>'blue-800'],
                        'void' => ['bg' => 'red-100', 'text'=>'red-800'],
                        'refund' => ['bg' => 'red-100', 'text' => 'red-800'],
                    ];
                    $c = $map[$status];
                    @endphp
                    <div class="card-inner p-4 flex justify-between items-center">
                        <div class="font-medium text-gray-900">{{ $t->passenger_name ?? $t->client->full_name }}</div>
                        <span class="uppercase text-[11px] font-semibold px-2.5 py-1 rounded-full ring-1 {{ "bg-{$c['bg']}" }} {{ "text-{$c['text']}" }} ring-current/30">
                            {{ ucfirst($status) }}
                        </span>
                    </div>
                @endforeach
                </div>
            </section>
            @endif
            @php
                $hotelPolicy   = $hotelDetail?->hotel_policy   ?? $hotelDetail?->hotel?->policy   ?? null;
                $bookingPolicy = $hotelDetail?->booking_policy ?? $tasks->first()?->booking_policy ?? null;
            @endphp
            @if($hotelPolicy || $bookingPolicy || $policies)
            <section class="page-break-inside-avoid card p-5">
                <h2 class="flex items-center text-[var(--brand-900)] text-lg font-semibold mb-4">
                <i class="fas fa-clipboard-check mr-2"></i>Hotel & Booking Policies
                </h2>
                <div class="space-y-4">
                    @if($hotelPolicy)
                    <div class="card-inner p-4">
                        <div class="font-semibold mb-2">Hotel Policy</div>
                        <div class="text-sm text-gray-800 whitespace-pre-line">{{ $hotelPolicy }}</div>
                    </div>
                    @endif
                    @if($bookingPolicy)
                    <div class="card-inner p-4">
                        <div class="font-semibold mb-2">Booking Policy</div>
                        <div class="text-sm text-gray-800 whitespace-pre-line">{{ $bookingPolicy }}</div>
                    </div>
                    @endif
                    @if($policies)
                    <div class="card-inner p-4 bg-yellow-50 border border-yellow-200">
                        <div class="flex items-center mb-2 text-yellow-800 font-semibold">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Cancellation Policy
                        </div>
                        @foreach($policies as $p)
                        <div class="grid grid-cols-2 gap-x-6 gap-y-2 mb-3 text-sm text-yellow-900">
                            @foreach($p as $field => $value)
                            <div><span class="font-medium">{{ ucwords(str_replace('_',' ',$field)) }}:</span> <span>{{ $value }}</span></div>
                            @endforeach
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </section>
            @endif
        </div>
        <div class="bg-gray-800 text-white text-center py-4 flex justify-between items-center px-8">
            <div class="text-sm opacity-75">
                © {{ date('Y') }} {{ $company?->name ?? 'City Travelers' }}. Voucher valid for the specified booking only.
            </div>
            <button onclick="window.print()" class="bg-amber-500 hover:bg-amber-600 px-4 py-2 rounded inline-flex items-center print:hidden">
                <i class="fas fa-download mr-2"></i>Download PDF
            </button>
        </div>
    </div>
</body>

</html>