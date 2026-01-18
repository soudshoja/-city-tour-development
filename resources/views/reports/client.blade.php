<x-app-layout>
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Client Report</h1>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium uppercase tracking-wide">Total Clients</p>
                        <p class="text-3xl font-bold mt-2">{{ number_format($clients->total()) }}</p>
                    </div>
                    <div class="bg-blue-400 bg-opacity-30 rounded-full p-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20h6M3 20h5v-2a4 4 0 013-3.87M6 8a6 6 0 1112 0A6 6 0 016 8z" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium uppercase tracking-wide">Total Owed</p>
                        <p class="text-3xl font-bold mt-2">{{ number_format(collect($allClients)->sum('total_owed'), 3) }} <span class="text-lg">KWD</span></p>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 rounded-full p-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 0V4m0 16v-4" />
                        </svg>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium uppercase tracking-wide">Total Paid</p>
                        <p class="text-3xl font-bold mt-2">{{ number_format(collect($allClients)->sum('total_paid'), 3) }} <span class="text-lg">KWD</span></p>
                    </div>
                    <div class="bg-purple-400 bg-opacity-30 rounded-full p-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 0V4m0 16v-4" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4 mb-4">
            <form method="GET" action="{{ route('reports.client') }}" class="space-y-4" id="filterForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                        <input type="date" name="date_from" id="date_from" value="{{ $dateFrom ?? '' }}" class="form-input w-full" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                        <input type="date" name="date_to" id="date_to" value="{{ $dateTo ?? '' }}" class="form-input w-full" />
                    </div>
                </div>
                <div class="flex gap-2 mt-4">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Filter</button>
                    <a href="{{ route('reports.client') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">Reset</a>
                    <button type="submit" formaction="{{ route('reports.client.pdf') }}" formtarget="_blank" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded">Export PDF</button>
                </div>
            </form>
        </div>

        <div class="p-4 overflow-x-auto bg-white rounded-lg shadow-md grid gap-4">
            <table class="min-w-full bg-white border border-gray-200">
                <thead>
                    <tr>
                        <th class="px-4 py-2 border-b">Client Name</th>
                        <th class="px-4 py-2 border-b">Total Owed (KWD)</th>
                        <th class="px-4 py-2 border-b">Total Paid (KWD)</th>
                        <th class="px-4 py-2 border-b">Balance (KWD)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clients as $item)
                    <tr class="hover:bg-gray-50 {{ $item['balance'] > 0 ? 'bg-red-50' : ($item['balance'] < 0 ? 'bg-green-50' : '') }}">
                        <td class="px-4 py-2 border-b">{{ $item['client']->full_name ?: $item['client']->name }}</td>
                        <td class="px-4 py-2 border-b">{{ number_format($item['total_owed'], 3) }}</td>
                        <td class="px-4 py-2 border-b">{{ number_format($item['total_paid'], 3) }}</td>
                        <td class="px-4 py-2 border-b font-bold">{{ number_format($item['balance'], 3) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-center text-gray-500">No clients found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            <x-pagination :data="$clients" />
        </div>
    </div>
</x-app-layout>
