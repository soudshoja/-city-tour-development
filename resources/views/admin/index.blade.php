<x-app-layout>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 p-6">
        <!-- Card 1 -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h5 class="text-sm font-semibold text-gray-500">Total Companies</h5>
            <h2 class="text-2xl font-bold mt-2">150</h2>
            <p class="text-gray-700 mt-2">Active companies currently registered.</p>
            <div class="mt-6 flex justify-center">
                <div class="relative w-24 h-24">
                    <svg class="w-full h-full" viewBox="0 0 36 36">
                        <circle
                            class="text-gray-300"
                            stroke-width="3"
                            fill="none"
                            cx="18"
                            cy="18"
                            r="15" />
                        <circle
                            class="text-blue-500"
                            stroke-dasharray="80, 100"
                            stroke-width="3"
                            fill="none"
                            cx="18"
                            cy="18"
                            r="15" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center text-blue-500 font-bold text-sm">
                        80%
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h5 class="text-sm font-semibold text-gray-500">Monthly Income</h5>
            <h2 class="text-2xl font-bold mt-2">$25,000</h2>
            <p class="text-gray-700 mt-2">Total income generated this month.</p>
            <div class="mt-6">
                <div class="w-full bg-gray-200 rounded-full h-4">
                    <div
                        class="bg-green-500 h-4 rounded-full"
                        style="width: 75%;"></div>
                </div>
                <p class="text-sm text-gray-500 mt-1">75% of target achieved</p>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h5 class="text-sm font-semibold text-gray-500">Pending Tasks</h5>
            <h2 class="text-2xl font-bold mt-2">42</h2>
            <p class="text-gray-700 mt-2">Tasks pending for resolution.</p>
            <div class="mt-6 flex justify-center">
                <div class="relative w-24 h-24">
                    <svg class="w-full h-full" viewBox="0 0 36 36">
                        <circle
                            class="text-gray-300"
                            stroke-width="3"
                            fill="none"
                            cx="18"
                            cy="18"
                            r="15" />
                        <circle
                            class="text-orange-500"
                            stroke-dasharray="50, 100"
                            stroke-width="3"
                            fill="none"
                            cx="18"
                            cy="18"
                            r="15" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center text-orange-500 font-bold text-sm">
                        50%
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 4 -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h5 class="text-sm font-semibold text-gray-500">New Signups</h5>
            <h2 class="text-2xl font-bold mt-2">85</h2>
            <p class="text-gray-700 mt-2">New user signups this week.</p>
            <div class="mt-6">
                <div class="w-full bg-gray-200 rounded-full h-4">
                    <div
                        class="bg-purple-500 h-4 rounded-full"
                        style="width: 50%;"></div>
                </div>
                <p class="text-sm text-gray-500 mt-1">50% of weekly goal</p>
            </div>
        </div>
    </div>
</x-app-layout>