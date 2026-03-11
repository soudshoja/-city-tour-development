<div class="relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] hover:bg-gray-300 rounded-full shadow-sm">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
        <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
        <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
    </svg>
</div>



<!-- white -->


<!-- company dashboard Old -->
<div x-data="{ activeMenu: null }" @click.outside="activeMenu = null" class="flex items-center" x-cloak>
    <!-- dashboard Button -->
    <div @click="activeMenu === 'dashboard' ? activeMenu = null : activeMenu = 'dashboard'"
        class="p-3 bg-white rounded-full shadow-md hover:bg-gray-300 flex cursor-pointer items-center justify-center">
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
    <div x-show="activeMenu === 'dashboard'"
        x-transition
        class="ml-2 p-3 bg-white rounded-full shadow-md hover:bg-gray-300 flex items-center justify-center">
        <a href="<?php echo e(route('dashboard')); ?>" class="text-sm">Dashboard</a>
    </div>
</div>
<!-- ./company dashboard Old -->

<!-- company dashboard New -->

<div class="flex flex-col items-center space-y-4">
    <!-- Dashboard Menu -->
    <div class="relative group">
        <!-- Icon Button -->
        <div
            class="p-3 bg-white rounded-full shadow-md hover:bg-gray-300/50 flex cursor-pointer items-center justify-center transition-all duration-200">
            <!-- SVG Icon -->
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <g fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="19" cy="5" r="2.5" />
                    <path stroke-linecap="round" d="M21.25 10v5.25a6 6 0 0 1-6 6h-6.5a6 6 0 0 1-6-6v-6.5a6 6 0 0 1 6-6H14" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="m7.27 15.045l2.205-2.934a.9.9 0 0 1 1.197-.225l2.151 1.359a.9.9 0 0 0 1.233-.261l2.214-3.34" />
                </g>
            </svg>
        </div>

        <!-- Tooltip (Dashboard Link) -->
        <a
            href="<?php echo e(route('dashboard')); ?>"
            class="p-3 absolute left-full top-1/2 hover:bg-gray-300/50 transform -translate-y-1/2 opacity-0 group-hover:opacity-100 group-hover:translate-x-2 bg-white text-gray-800 text-sm rounded-full shadow-md transition-all duration-200">
            Dashboard
        </a>
    </div>
</div>

<!-- ./company dashboard New -->



<!-- company users LIST -->
<div class="flex flex-col items-center space-y-4">
    <!-- Menu item-->
    <div class="relative group">
        <!-- Icon Button -->
        <div class="p-3 bg-white rounded-full shadow-md hover:bg-gray-300/50 flex cursor-pointer items-center justify-center transition-all duration-200">
            <!-- Notification Bell SVG Icon -->
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <g fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="9" cy="6" r="4" />
                    <path stroke-linecap="round" d="M15 9a3 3 0 1 0 0-6" />
                    <ellipse cx="9" cy="17" rx="7" ry="4" />
                    <path stroke-linecap="round" d="M18 14c1.754.385 3 1.359 3 2.5c0 1.03-1.014 1.923-2.5 2.37" />
                </g>
            </svg>
        </div>

        <!-- Tooltip Links -->
        <div class="flex flex-col space-y-2  absolute left-full top-1/2  transform -translate-y-1/2 
              opacity-0 group-hover:opacity-100 group-hover:translate-x-2 bg-white text-gray-800 text-sm rounded-lg shadow-md transition-all duration-200 inline whitespace-nowrap">
            <a href="<?php echo e(route('agents.index')); ?>" class="hover:bg-gray-300 hover:rounded-t-lg p-3">
                Agents List
            </a>
            <a href="<?php echo e(route('clients.index')); ?>" class="hover:bg-gray-300 hover:rounded-b-lg p-3">
                Clients List
            </a>
        </div>
    </div>
</div>

<!-- ./company users -->




<div class="flex flex-col items-center space-y-4">
    <!-- Menu item-->
    <div class="relative group">
        <!-- Icon Button -->
        <div
            class="p-3 bg-white rounded-full shadow-md hover:bg-gray-300/50 flex cursor-pointer items-center justify-center transition-all duration-200">
            <!-- SVG Icon -->
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <g fill="none">
                    <path stroke="currentColor" d="M9 6a3 3 0 1 0 6 0a3 3 0 0 0-6 0Zm-4.562 7.902a3 3 0 1 0 3 5.195a3 3 0 0 0-3-5.196Zm15.124 0a2.999 2.999 0 1 1-2.998 5.194a2.999 2.999 0 0 1 2.998-5.194Z" />
                    <path fill="currentColor" fill-rule="evenodd" d="M9.003 6.125a3 3 0 0 1 .175-1.143a8.5 8.5 0 0 0-5.031 4.766a8.5 8.5 0 0 0-.502 4.817a3 3 0 0 1 .902-.723a7.5 7.5 0 0 1 4.456-7.717m5.994 0a7.5 7.5 0 0 1 4.456 7.717q.055.028.11.06c.3.174.568.398.792.663a8.5 8.5 0 0 0-5.533-9.583a3 3 0 0 1 .175 1.143m2.536 13.328a3 3 0 0 1-1.078-.42a7.5 7.5 0 0 1-8.91 0l-.107.065a3 3 0 0 1-.971.355a8.5 8.5 0 0 0 11.066 0" clip-rule="evenodd" />
                </g>
            </svg>
        </div>


        <a
            href="<?php echo e(route('branches.index')); ?>"
            class="p-3 absolute left-full top-1/2 hover:bg-gray-300 transform -translate-y-1/2 opacity-0 group-hover:opacity-100 group-hover:translate-x-2 bg-white text-gray-800 text-sm rounded-full shadow-md transition-all duration-200 inline whitespace-nowrap">
            Branches List
        </a>
    </div>
</div><?php /**PATH /home/soudshoja/soud-laravel/resources/views/components/cityIcons.blade.php ENDPATH**/ ?>