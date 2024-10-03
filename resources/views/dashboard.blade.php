<x-app-layout>
    <style>
    .bgCard {
        background: url("{{ asset('images/bgCardCity.png') }}") no-repeat center center;
        background-size: cover;
    }
    </style>
    @if(Auth()->user()->role === 'admin')
    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">

        <div
            class="bgCard w-full flex-2 bg-cover bg-center rounded-xl p-6 shadow-lg text-white flex flex-col items-center justify-center">
            <div class="text-xl">Total Invoice</div>
            <div class="text-5xl font-extrabold mt-2">{{ number_format($totalInvoiceAmount, 2) }} {{ $invoices->first()->currency ?? 'USD' }}</div>
            <div class="text-green-500 text-lg mt-4">▲ $343.23</div>
        </div>

        <div class="col-span-1 md:col-span-2 flex flex-wrap gap-3">
            <div class="flex-1 bg-gray-900 rounded-xl p-6 shadow-lg text-white text-center w-full sm:w-1/2 lg:w-1/4">
                <div class="flex items-center justify-center bg-gray-700 w-12 h-12 rounded-full mx-auto mb-4">
                    <svg class="w-8 h-8" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="30" cy="18" r="8" stroke="#AAB3D1" stroke-width="3" />
                        <path opacity="0.5" d="M42 24C45.3137 24 48 21.7614 48 19C48 16.2386 45.3137 14 42 14"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <path opacity="0.5" d="M18 24C14.6863 24 12 21.7614 12 19C12 16.2386 14.6863 14 18 14"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <ellipse cx="30" cy="40" rx="12" ry="8" stroke="#AAB3D1" stroke-width="3" />
                        <path opacity="0.5" d="M46 44C49.5085 43.2306 52 41.2821 52 39C52 36.7179 49.5085 34.7694 46 34"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <path opacity="0.5" d="M14 44C10.4915 43.2306 8 41.2821 8 39C8 36.7179 10.4915 34.7694 14 34"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="text-sm">Agents</div>
                <div class="text-3xl font-extrabold mt-2">{{$agentCount }}</div>
                <div class="text-sm mt-2">- <span class="text-red-500">11.2%</span> on avg</div>
            </div>

            <div class="flex-1 bg-gray-900 rounded-xl p-6 shadow-lg text-white text-center w-full sm:w-1/2 lg:w-1/4">
                <div class="flex items-center justify-center bg-gray-700 w-12 h-12 rounded-full mx-auto mb-4">
                    <svg class="w-8 h-8" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="30" cy="18" r="8" stroke="#AAB3D1" stroke-width="3" />
                        <path opacity="0.5" d="M42 24C45.3137 24 48 21.7614 48 19C48 16.2386 45.3137 14 42 14"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <path opacity="0.5" d="M18 24C14.6863 24 12 21.7614 12 19C12 16.2386 14.6863 14 18 14"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <ellipse cx="30" cy="40" rx="12" ry="8" stroke="#AAB3D1" stroke-width="3" />
                        <path opacity="0.5" d="M46 44C49.5085 43.2306 52 41.2821 52 39C52 36.7179 49.5085 34.7694 46 34"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <path opacity="0.5" d="M14 44C10.4915 43.2306 8 41.2821 8 39C8 36.7179 10.4915 34.7694 14 34"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="text-sm">Clients</div>
                <div class="text-3xl font-extrabold mt-2">{{$clientCount}}</div>
                <div class="text-sm mt-2">- <span class="text-red-500">11.2%</span> on avg</div>
            </div>

            <div class="flex-1 bg-gray-900 rounded-xl p-6 shadow-lg text-white text-center w-full sm:w-1/2 lg:w-1/4">
                <div class="flex items-center justify-center bg-gray-700 w-12 h-12 rounded-full mx-auto mb-4">
                    <svg class="w-8 h-8" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="30" cy="18" r="8" stroke="#AAB3D1" stroke-width="3" />
                        <path opacity="0.5" d="M42 24C45.3137 24 48 21.7614 48 19C48 16.2386 45.3137 14 42 14"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <path opacity="0.5" d="M18 24C14.6863 24 12 21.7614 12 19C12 16.2386 14.6863 14 18 14"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <ellipse cx="30" cy="40" rx="12" ry="8" stroke="#AAB3D1" stroke-width="3" />
                        <path opacity="0.5" d="M46 44C49.5085 43.2306 52 41.2821 52 39C52 36.7179 49.5085 34.7694 46 34"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <path opacity="0.5" d="M14 44C10.4915 43.2306 8 41.2821 8 39C8 36.7179 10.4915 34.7694 14 34"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="text-sm">Tasks</div>
                <div class="text-3xl font-extrabold mt-2">{{$taskCount}}</div>
                <div class="text-sm mt-2">- <span class="text-red-500">11.2%</span> on avg </div>
            </div>

            <div class="flex-1 bg-gray-900 rounded-xl p-6 shadow-lg text-white text-center w-full sm:w-1/2 lg:w-1/4">
                <div class="flex items-center justify-center bg-gray-700 w-12 h-12 rounded-full mx-auto mb-4">
                    <svg class="w-8 h-8" viewBox="0 0 60 60" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="30" cy="18" r="8" stroke="#AAB3D1" stroke-width="3" />
                        <path opacity="0.5" d="M42 24C45.3137 24 48 21.7614 48 19C48 16.2386 45.3137 14 42 14"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <path opacity="0.5" d="M18 24C14.6863 24 12 21.7614 12 19C12 16.2386 14.6863 14 18 14"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <ellipse cx="30" cy="40" rx="12" ry="8" stroke="#AAB3D1" stroke-width="3" />
                        <path opacity="0.5" d="M46 44C49.5085 43.2306 52 41.2821 52 39C52 36.7179 49.5085 34.7694 46 34"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                        <path opacity="0.5" d="M14 44C10.4915 43.2306 8 41.2821 8 39C8 36.7179 10.4915 34.7694 14 34"
                            stroke="#AAB3D1" stroke-width="3" stroke-linecap="round" />
                    </svg>
                </div>
                <div class="text-sm">Companies</div>
                <div class="text-3xl font-extrabold mt-2">{{$companyCount}}</div>
                <div class="text-sm mt-2">- <span class="text-red-500">11.2%</span> on avg</div>
            </div>
        </div>
    </div>


    <div class="mt-5 bg-gray-900 rounded-lg p-4">
        <div class="flex justify-between items-center mb-2">
            <h2 class="text-white text-xl font-bold">Revenue</h2>

            <button class="btnClight">View Report</button>
        </div>
        <p class="text-gray-400 text-sm">Data from 1-12 Apr, 2024</p>
        <div class="mt-4">
            <canvas id="revenueChart"></canvas>
        </div>
        <div class="flex gap-3 mt-4">
            <div class="flex items-center">
                <div class="w-4 h-4 rounded-full bg-green-500 mr-2"></div>
                <p class="text-gray-400 text-sm">Income</p>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 rounded-full bg-[#febd5e] mr-2"></div>
                <p class="text-gray-400 text-sm">Expense</p>
            </div>
        </div>
    </div>

    <div class="mt-5 bg-gray-900 rounded-lg">
        <div class="output-console"></div>
    </div>

    @elseif(Auth()->user()->role == 'company')
    <div class="">
        @include('companies.index')
    </div>

    @elseif(Auth()->user()->role == 'agent')
    <div class="">
        @include('items.index')
    </div>
    @endif


</x-app-layout>