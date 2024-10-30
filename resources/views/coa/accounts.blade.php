<x-app-layout>
    <div class="p-8 bg-gray-100 min-h-screen">
        <!-- Top Card Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- Card 1 -->
            <div class="bg-blue-100 p-6 rounded-lg shadow">
                <h3 class="font-semibold text-lg mb-2">Assets </h3>
                <p class="text-sm text-gray-500 mb-4">you can add anything related to Assets here</p>
                <div class="flex space-x-2">
                    <button class="bg-blue-950 text-white px-4 py-2 rounded-lg">Add New</button>
                    <button class="border border-blue-950 text-gray-700 px-4 py-2 rounded-lg">See List</button>
                </div>
            </div>

            <!-- Card 2 -->
            <div class="bg-green-100 p-6 rounded-lg shadow">
                <h3 class="font-semibold text-lg mb-2">Liabilities</h3>
                <p class="text-sm text-gray-500 mb-4">you can add anything related to Liabilities here
                </p>
                <div class="flex space-x-2">
                    <button class="bg-green-950 text-white px-4 py-2 rounded-lg">Add New</button>
                    <button class="border border-green-950 text-gray-700 px-4 py-2 rounded-lg">See List</button>
                </div>
            </div>

            <!-- Card 3 -->
            <div class="bg-yellow-100 p-6 rounded-lg shadow">
                <h3 class="font-semibold text-lg mb-2">Income</h3>
                <p class="text-sm text-gray-500 mb-4">you can add anything related to Income here
                </p>
                <div class="flex space-x-2">
                    <button class="bg-yellow-950 text-white px-4 py-2 rounded-lg">Add New</button>
                    <button class="border border-yellow-950 text-gray-700 px-4 py-2 rounded-lg">See List</button>
                </div>
            </div>

            <!-- Card 4 -->
            <div class="bg-red-100 p-6 rounded-lg shadow">
                <h3 class="font-semibold text-lg mb-2">Expenses</h3>
                <p class="text-sm text-gray-500 mb-4">you can add anything related to Expenses here
                </p>
                <div class="flex space-x-2">
                    <button class="bg-red-950 text-white px-4 py-2 rounded-lg">Add New</button>
                    <button class="border border-red-950 text-gray-700 px-4 py-2 rounded-lg">See List</button>
                </div>
            </div>

        </div>

        <!-- Integrations Table Section -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="font-semibold text-lg mb-4">You have 3 integrations</h3>
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b">
                        <th class="py-3 text-sm font-medium text-gray-500">Integration</th>
                        <th class="py-3 text-sm font-medium text-gray-500">Status</th>
                        <th class="py-3 text-sm font-medium text-gray-500">Type</th>
                        <th class="py-3 text-sm font-medium text-gray-500">Version</th>
                        <th class="py-3 text-sm font-medium text-gray-500">Date</th>
                        <th class="py-3 text-sm font-medium text-gray-500">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Row 1 -->
                    <tr class="border-b">
                        <td class="py-4 text-gray-700">Weather API</td>
                        <td class="py-4"><span
                                class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm">Scheduler</span></td>
                        <td class="py-4 text-gray-700">Manual Trigger</td>
                        <td class="py-4 text-gray-700">V 1.0.0</td>
                        <td class="py-4 text-gray-700">1 hour</td>
                        <td class="py-4 flex space-x-2">
                            <button class="text-gray-400 hover:text-gray-700">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h6.5a1.5 1.5 0 000-3H4V5h6.5a1.5 1.5 0 100-3H4zm11.5 3a1.5 1.5 0 000 3h1.293l-3.147 3.146a.5.5 0 00.708.708L17.5 9.707V11.5a1.5 1.5 0 103 0V6.5a1.5 1.5 0 00-1.5-1.5h-4z" />
                                </svg>
                            </button>
                            <button class="text-gray-400 hover:text-gray-700">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zM3 15V5a1 1 0 011-1h12a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1zm5-3a3 3 0 116 0H8zm0 2h6v2H8v-2z" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                    <!-- More rows as needed -->
                </tbody>
            </table>
        </div>
    </div>


</x-app-layout>