<!-- admin dashboard -->
<div x-data="{ dashboard: false }" class="flex items-center">
    <!-- dashboard Button -->
    <div @click="dashboard = !dashboard" class="p-3 bg-white rounded-full shadow-md hover:bg-gray-300 flex cursor-pointer items-center justify-center">
        <!-- dashboard Icon -->
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <g fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="19" cy="5" r="2.5" />
                <path stroke-linecap="round" d="M21.25 10v5.25a6 6 0 0 1-6 6h-6.5a6 6 0 0 1-6-6v-6.5a6 6 0 0 1 6-6H14" />
                <path stroke-linecap="round" stroke-linejoin="round" d="m7.27 15.045l2.205-2.934a.9.9 0 0 1 1.197-.225l2.151 1.359a.9.9 0 0 0 1.233-.261l2.214-3.34" />
            </g>
        </svg>
    </div>

    <!-- Create dashboard link -->
    <div x-show="dashboard" class="ml-2 p-3 bg-white rounded-full shadow-md hover:bg-gray-300 flex items-center justify-center">
        <a href="{{ route('dashboard') }}" class="text-sm">Dashboard</a>
    </div>
</div>
<!-- ./admin dashboard -->

<!-- Add New -->
<div x-data="{ AddNew: false }" class="flex items-center">
    <!-- AddNew Button -->
    <div @click="AddNew = !AddNew" class="p-3 bg-white rounded-full shadow-md hover:bg-gray-300 flex cursor-pointer items-center justify-center">
        <!-- AddNew Icon -->
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h4m4 0h-4m0 0V8m0 4v4m0 6c5.523 0 10-4.477 10-10S17.523 2 12 2S2 6.477 2 12s4.477 10 10 10" />
        </svg>


    </div>

    <!-- Create AddNew link -->
    <div x-show="AddNew" class="w-full">
        <a href="{{ route('companiesnew.new') }}" class="MaxW mb-2 text-sm ml-2 p-3 bg-white rounded-full shadow-md hover:bg-gray-300 flex items-center justify-center">Add New</a>
    </div>
</div>
<!-- ./Add New -->




<!-- users LIST-->
<div x-data="{ users: false }" class="flex items-center">
    <!-- users Button -->
    <div @click="users = !users" class="p-3 bg-white rounded-full shadow-md hover:bg-gray-300 flex cursor-pointer items-center justify-center">
        <!-- users Icon -->
        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
            <g fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="9" cy="6" r="4" />
                <path stroke-linecap="round" d="M15 9a3 3 0 1 0 0-6" />
                <ellipse cx="9" cy="17" rx="7" ry="4" />
                <path stroke-linecap="round" d="M18 14c1.754.385 3 1.359 3 2.5c0 1.03-1.014 1.923-2.5 2.37" />
            </g>
        </svg>

    </div>

    <!-- users link -->
    <div x-show="users" class="w-full">
        <a href="{{ route('companies.index') }}" class="MaxW mb-2 text-sm ml-2 p-3 bg-white rounded-full shadow-md hover:bg-gray-300 flex items-center justify-center">Companies List</a>

    </div>
</div>
<!-- ./ users -->