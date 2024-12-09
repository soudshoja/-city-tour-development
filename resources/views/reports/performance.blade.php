<x-app-layout>
<div class="container mx-auto p-6">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Performance Summary</h1>

    <!-- Agents Performance Summary -->
    <section class="mb-12">
        <h2 class="text-2xl font-semibold text-gray-700 mb-4">Agent Performance Summary</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($agents as $agent)
            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800">{{ $agent->agent_name }}</h3>
                <p class="text-gray-500 text-sm mb-4">Agent ID: {{ $agent->id }}</p>
                <div class="mt-4">
                    <p class="text-gray-600"><strong>Total Transactions:</strong> {{ $agent->total_transactions }}</p>
                    <p class="text-gray-600"><strong>Total Debit:</strong> ${{ number_format($agent->total_debit, 2) }}</p>
                    <p class="text-gray-600"><strong>Total Credit:</strong> ${{ number_format($agent->total_credit, 2) }}</p>
                    <p class="text-gray-600"><strong>Balance:</strong> ${{ number_format($agent->balance, 2) }}</p>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-sm text-gray-500">{{ $agent->performance_score > 0 ? 'Good Performance' : 'Needs Improvement' }}</span>
                    <div class="bg-blue-100 text-blue-500 text-xs font-semibold px-2 py-1 rounded">
                        Performance Score: {{ $agent->performance_score }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>

    <!-- Clients Performance Summary -->
    <section>
        <h2 class="text-2xl font-semibold text-gray-700 mb-4">Client Performance Summary</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($clients as $client)
            <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200">
                <h3 class="text-xl font-semibold text-gray-800">{{ $client->client_name }}</h3>
                <p class="text-gray-500 text-sm mb-4">Client ID: {{ $client->id }}</p>
                <div class="mt-4">
                    <p class="text-gray-600"><strong>Total Transactions:</strong> {{ $client->total_transactions }}</p>
                    <p class="text-gray-600"><strong>Total Debit:</strong> ${{ number_format($client->total_debit, 2) }}</p>
                    <p class="text-gray-600"><strong>Total Credit:</strong> ${{ number_format($client->total_credit, 2) }}</p>
                    <p class="text-gray-600"><strong>Balance:</strong> ${{ number_format($client->balance, 2) }}</p>
                    <p class="text-gray-600"><strong>Payment Status:</strong> {{ $client->is_good_payer ? 'Good' : 'Poor' }}</p>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <span class="text-sm text-gray-500">{{ $client->is_good_payer ? 'Reliable Client' : 'Late Payments' }}</span>
                    <div class="bg-green-100 text-green-500 text-xs font-semibold px-2 py-1 rounded">
                        Rating: {{ $client->client_rating }}/5
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>
</div>
</x-app-layout>