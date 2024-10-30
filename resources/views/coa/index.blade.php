<x-app-layout>
<div class="container mx-auto p-6 bg-gray-100 rounded-lg shadow-lg">
    <h1 class="text-2xl font-bold mb-4">Financial Statement</h1>

    <!-- Filters Section -->
    <div class="flex items-center mb-4">
        <label class="mr-2 font-semibold">View:</label>
        <select class="border rounded px-3 py-2 mr-4">
            @foreach($views as $view)
                <option>{{ $view }}</option>
            @endforeach
        </select>

        <label class="mr-2 font-semibold">Filter:</label>
        <select class="border rounded px-3 py-2 mr-4">
            <option>This Month (Period) vs Budget This Month (Period)</option>
        </select>

        <label class="mr-2 font-semibold">Period:</label>
        <select class="border rounded px-3 py-2">
            @foreach($periods as $period)
                <option>{{ $period }}</option>
            @endforeach
        </select>
        
        <button class="ml-4 px-4 py-2 bg-blue-500 text-white rounded">Refresh</button>
    </div>

    <!-- Table Section -->
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200">
            <thead>
                <tr>
                    <th class="py-2 px-4 border-b font-semibold text-left">Account Name</th>
                    <th class="py-2 px-4 border-b font-semibold text-left">Account Code</th>
                    <th class="py-2 px-4 border-b font-semibold text-right">T-M Dr (Actual)</th>
                    <th class="py-2 px-4 border-b font-semibold text-right">T-M Cr (Actual)</th>
                    <th class="py-2 px-4 border-b font-semibold text-right">Balance (Actual)</th>
                    <th class="py-2 px-4 border-b font-semibold text-right">T-M Dr (Budget)</th>
                    <th class="py-2 px-4 border-b font-semibold text-right">T-M Cr (Budget)</th>
                    <th class="py-2 px-4 border-b font-semibold text-right">Balance (Budget)</th>
                    <th class="py-2 px-4 border-b font-semibold text-right">Variance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts as $account)
                    @include('coa.partials.account-row', ['account' => $account, 'level' => 0])
                @endforeach
            </tbody>
        </table>
    </div>
</div>
</x-app-layout>