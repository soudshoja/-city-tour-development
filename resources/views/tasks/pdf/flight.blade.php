<!DOCTYPE html>
<html lang="en" class="antialiased">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Flight Voucher: {{ $tasks->first()->gds_reference ?: $tasks->first()->reference }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('images/City0logo.svg') }}" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <style>
        @media print {
            .page-break-inside-avoid {
                page-break-inside: avoid;
            }

            .print\\:hidden {
                display: none !important;
            }
            *, ::before, ::after {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-900 font-sans p-6 flex items-center justify-center min-h-screen">
    <div class="container max-w-3xl bg-white rounded-lg shadow-lg overflow-hidden page-break-inside-avoid">
        <div class="bg-blue-800 text-white px-8 py-6 flex justify-between items-center border-b-4 border-yellow-500">
            <div class="flex items-center space-x-4">
                <img src="{{ asset('images/City0logo.svg') }}" alt="Logo" class="h-12 w-12" />
                <div>
                    <h1 class="text-xl font-bold">{{ $tasks->first()->company->name }}</h1>
                    <p class="text-sm opacity-75">Your Trusted Travel Partner</p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-3xl font-extrabold tracking-wider">
                    {{ $tasks->first()->gds_reference ?: $tasks->first()->reference }}
                </div>
                <div class="text-sm uppercase opacity-75 mt-1">
                    Flight Voucher
                </div>
            </div>
        </div>

        <div class="p-8 space-y-8">
            <section class="page-break-inside-avoid">
                <h2 class="flex items-center text-blue-800 text-lg font-semibold mb-4">
                    <i class="fas fa-user-tie mr-2"></i>Agent Information
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <div class="text-xs uppercase text-gray-500">Agent Name</div>
                        <div class="text-sm font-medium text-gray-900">
                            {{ $tasks->first()->agent->name ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Agent Email</div>
                        <div class="text-sm font-medium text-gray-900">
                            @if($tasks->first()->agent->email)
                            <a
                                href="mailto:{{ $tasks->first()->agent->email }}"
                                class="text-blue-600 hover:underline">
                                {{ $tasks->first()->agent->email }}
                            </a>
                            @else
                            —
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Agent Phone</div>
                        <div class="text-sm font-medium text-gray-900">
                            {{ $tasks->first()->agent->phone_number ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Issued Date</div>
                        <div class="text-sm font-medium text-gray-900">
                            {{ $tasks->first()->issued_date->format('d M Y') }}
                        </div>
                    </div>
                </div>
            </section>
            <section class="page-break-inside-avoid">
                <h2 class="flex items-center text-blue-800 text-lg font-semibold mb-4">
                    <i class="fas fa-id-card mr-2"></i>Client Information
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <div class="text-xs uppercase text-gray-500">Client Name</div>
                        <div class="text-sm font-medium text-gray-900">
                            {{ $tasks->first()->client->first_name ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-gray-500">Client Email</div>
                        <div class="text-sm font-medium text-gray-900">
                            @if(optional($tasks->first()->client)->email)
                            <a href="mailto:{{ $tasks->first()->client->email }}" class="text-blue-600 hover:underline">
                                {{ $tasks->first()->client->email }}
                            </a>
                            @else
                            —
                            @endif
                        </div>
                    </div>
                </div>
            </section>
            <section class="page-break-inside-avoid">
                <h2 class="flex items-center text-blue-800 text-lg font-semibold mb-4">
                    <i class="fas fa-route mr-2"></i>Flight Segments
                </h2>
                <div class="relative ml-6 border-l-2 border-gray-200">
                    @foreach($flights as $i => $seg)
                    <div class="mb-8 pl-6 relative">
                        <span class="absolute -left-3 top-1 bg-blue-800 text-white w-6 h-6 rounded-full flex items-center justify-center text-sm">
                            {{ $i + 1 }}
                        </span>
                        <div class="bg-white p-4 shadow-sm rounded-lg space-y-4">
                            <div class="flex justify-between items-center">
                                <div class="font-semibold text-gray-900">
                                    {{ $seg->airport_from ?? 'N/A' }}
                                    <i class="fas fa-plane-departure text-lg text-yellow-500 mx-2"></i>
                                    {{ $seg->airport_to   ?? 'N/A' }}
                                </div>
                                <div class="text-xs uppercase text-gray-500">
                                    Segment {{ $i + 1 }}
                                </div>
                            </div>
                            <!-- <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <div class="uppercase text-xs text-gray-500">Country From</div>
                                    <div class="font-medium text-gray-900">
                                        {{ optional($seg->countryFrom)->name ?? 'Unknown' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="uppercase text-xs text-gray-500">Country To</div>
                                    <div class="font-medium text-gray-900">
                                        {{ optional($seg->countryTo)->name ?? 'Unknown' }}
                                    </div>
                                </div>
                            </div> -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <div class="uppercase text-xs text-gray-500">Depart</div>
                                    <div class="font-medium text-gray-900">
                                        {{ $seg->departure_time ? \Carbon\Carbon::parse($seg->departure_time)->format('d M Y, H:i') : 'Not Set' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="uppercase text-xs text-gray-500">Arrive</div>
                                    <div class="font-medium text-gray-900">
                                        {{ $seg->arrival_time ? \Carbon\Carbon::parse($seg->arrival_time)->format('d M Y, H:i') : 'Not Set' }}
                                    </div>
                                </div>

                                <div>
                                    <div class="uppercase text-xs text-gray-500">Duration</div>
                                    <div class="font-medium text-gray-900">
                                        {{ $seg->duration_time ?? 'Not Set' }}
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <div class="uppercase text-xs text-gray-500">Terminal From</div>
                                    <div class="font-medium text-gray-900">
                                        {{ $seg->terminal_from ?? 'TBD' }}
                                    </div>
                                </div>
                                <div>
                                    <div class="uppercase text-xs text-gray-500">Terminal To</div>
                                    <div class="font-medium text-gray-900">
                                        {{ $seg->terminal_to ?? 'TBD' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </section>

            <section class="page-break-inside-avoid">
                <h2 class="flex items-center text-blue-800 text-xl font-semibold mb-6">
                    <i class="fas fa-users mr-2"></i>Passengers
                </h2>
                <div class="space-y-6">
                    @foreach($tasks as $t)
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                        <div class="bg-gray-50 px-6 py-3 flex justify-between items-center">
                            <div class="flex items-center space-x-3">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ $t->passenger_name }}
                                </h3>
                                <span class="inline-flex items-center text-xs font-semibold bg-green-100 text-green-800 px-2 py-0.5 rounded">
                                    {{ ucfirst($t->status ?? 'confirmed') }}
                                </span>
                            </div>
                            <div class="text-sm text-blue-600 font-medium">
                                Ticket: {{ $t->ticket_number }}
                            </div>
                        </div>
                        <div class="px-6 py-4 grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm">
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-chair mt-1 text-gray-400"></i>
                                <div>
                                    <div class="uppercase text-xs text-gray-500">Seat</div>
                                    <div class="mt-1 font-medium text-gray-900">
                                        {{ optional($t->flightDetails)->seat_no ?: 'TBA' }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-suitcase-rolling mt-1 text-gray-400"></i>
                                <div>
                                    <div class="uppercase text-xs text-gray-500">Baggage</div>
                                    <div class="mt-1 font-medium text-gray-900">
                                        {{ optional($t->flightDetails)->baggage_allowed ?? 'Standard allowance' }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-utensils mt-1 text-gray-400"></i>
                                <div>
                                    <div class="uppercase text-xs text-gray-500">Meal</div>
                                    <div class="mt-1 font-medium text-gray-900">
                                        {{ optional($t->flightDetails)->flight_meal ?? 'On request' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </section>
            <!-- <section class="page-break-inside-avoid">
                <div class="bg-yellow-100 border-l-4 border-yellow-500 p-4 text-yellow-800 text-sm">
                    Please arrive at the airport at least 2 hours before domestic flights and 3 hours before international flights. Voucher must be presented with valid ID.
                </div>
            </section> -->
        </div>
        <div class="bg-gray-800 text-white text-center py-4 flex justify-between items-center px-8">
            <div class="text-sm opacity-75">
                © {{ date('Y') }} City Travelers. Voucher valid for the specified flight only.
            </div>
            <button
                onclick="window.print()"
                class="bg-yellow-500 hover:bg-yellow-600 text-white font-semibold px-4 py-2 rounded print:hidden">
                <i class="fas fa-download mr-2"></i>Download PDF
            </button>
        </div>
    </div>
</body>

</html>