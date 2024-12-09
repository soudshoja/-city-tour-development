 <!-- company dashboard -->
 <div x-data="{ open: false }" x-cloak class="relative">
     <a @mouseenter="open = true" @mouseleave="open = false"
         class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
         href="#">
         <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
             class="shrink-0">
             <path opacity="0.5"
                 d="M2 12.2039C2 9.91549 2 8.77128 2.5192 7.82274C3.0384 6.87421 3.98695 6.28551 5.88403 5.10813L7.88403 3.86687C9.88939 2.62229 10.8921 2 12 2C13.1079 2 14.1106 2.62229 16.116 3.86687L18.116 5.10812C20.0131 6.28551 20.9616 6.87421 21.4808 7.82274C22 8.77128 22 9.91549 22 12.2039V13.725C22 17.6258 22 19.5763 20.8284 20.7881C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.7881C2 19.5763 2 17.6258 2 13.725V12.2039Z"
                 fill="currentColor" class="text-gray-700 dark:text-gray-300"></path>
             <path
                 d="M9 17.25C8.58579 17.25 8.25 17.5858 8.25 18C8.25 18.4142 8.58579 18.75 9 18.75H15C15.4142 18.75 15.75 18.4142 15.75 18C15.75 17.5858 15.4142 17.25 15 17.25H9Z"
                 fill="currentColor" class="text-gray-700 dark:text-gray-300"></path>
         </svg> <span class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Dashboard</span>
         <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
             <path fill-rule="evenodd"
                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                 clip-rule="evenodd" />
         </svg>
     </a>
     <!-- Dropdown Menu -->
     <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
         class="absolute  z-10 mt-2 w-36 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
         <div class="py-1">
             <a href="{{ route('dashboard') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Revenue</a>
             <a href="#"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Report</a>
         </div>
     </div>
 </div>

 <!-- company tasks -->
 <div x-data="{ open: false }" x-cloak class="relative">
     <a @mouseenter="open = true" @mouseleave="open = false"
         class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
         href="#">
         <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="shrink-0">
             <path fill-rule="evenodd" clip-rule="evenodd" d="M17.5 2.75C17.9142 2.75 18.25 3.08579 18.25 3.5V5.75H20.5C20.9142 5.75 21.25 6.08579 21.25 6.5C21.25 6.91421 20.9142 7.25 20.5 7.25H18.25V9.5C18.25 9.91421 17.9142 10.25 17.5 10.25C17.0858 10.25 16.75 9.91421 16.75 9.5V7.25H14.5C14.0858 7.25 13.75 6.91421 13.75 6.5C13.75 6.08579 14.0858 5.75 14.5 5.75H16.75V3.5C16.75 3.08579 17.0858 2.75 17.5 2.75Z" fill="currentColor" class="text-gray-700 dark:text-gray-300" />
             <path d="M2 6.5C2 4.37868 2 3.31802 2.65901 2.65901C3.31802 2 4.37868 2 6.5 2C8.62132 2 9.68198 2 10.341 2.65901C11 3.31802 11 4.37868 11 6.5C11 8.62132 11 9.68198 10.341 10.341C9.68198 11 8.62132 11 6.5 11C4.37868 11 3.31802 11 2.65901 10.341C2 9.68198 2 8.62132 2 6.5Z" fill="currentColor" class="text-gray-700 dark:text-gray-300" />
             <path d="M13 17.5C13 15.3787 13 14.318 13.659 13.659C14.318 13 15.3787 13 17.5 13C19.6213 13 20.682 13 21.341 13.659C22 14.318 22 15.3787 22 17.5C22 19.6213 22 20.682 21.341 21.341C20.682 22 19.6213 22 17.5 22C15.3787 22 14.318 22 13.659 21.341C13 20.682 13 19.6213 13 17.5Z" fill="currentColor" class="text-gray-700 dark:text-gray-300" />
             <path opacity="0.5" d="M2 17.5C2 15.3787 2 14.318 2.65901 13.659C3.31802 13 4.37868 13 6.5 13C8.62132 13 9.68198 13 10.341 13.659C11 14.318 11 15.3787 11 17.5C11 19.6213 11 20.682 10.341 21.341C9.68198 22 8.62132 22 6.5 22C4.37868 22 3.31802 22 2.65901 21.341C2 20.682 2 19.6213 2 17.5Z" fill="currentColor" class="text-gray-700 dark:text-gray-300" />
         </svg>
         <span class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Tasks</span>
         <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
             <path fill-rule="evenodd"
                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                 clip-rule="evenodd" />
         </svg>
     </a>
     <!-- Dropdown Menu -->
     <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
         class="absolute  z-10 mt-2 w-36 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
         <div class="py-1">

             <a href="{{ route('tasks.index') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Tasks
                 List</a>
                 <a href="{{ route('tasks.voucher') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Tasks
                 Voucher</a>
         </div>
     </div>
 </div>

 <!-- company finances -->
 <div x-data="{ open: false }" x-cloak class="relative">
     <a @mouseenter="open = true" @mouseleave="open = false"
         class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
         href="#">
         <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="shrink-0">
             <path fill-rule="evenodd" clip-rule="evenodd" d="M12.052 1.25H11.948C11.0495 1.24997 10.3003 1.24995 9.70552 1.32991C9.07773 1.41432 8.51093 1.59999 8.05546 2.05546C7.59999 2.51093 7.41432 3.07773 7.32991 3.70552C7.27259 4.13189 7.25637 5.15147 7.25179 6.02566C5.22954 6.09171 4.01536 6.32778 3.17157 7.17157C2 8.34315 2 10.2288 2 14C2 17.7712 2 19.6569 3.17157 20.8284C4.34314 22 6.22876 22 9.99998 22H14C17.7712 22 19.6569 22 20.8284 20.8284C22 19.6569 22 17.7712 22 14C22 10.2288 22 8.34315 20.8284 7.17157C19.9846 6.32778 18.7705 6.09171 16.7482 6.02566C16.7436 5.15147 16.7274 4.13189 16.6701 3.70552C16.5857 3.07773 16.4 2.51093 15.9445 2.05546C15.4891 1.59999 14.9223 1.41432 14.2945 1.32991C13.6997 1.24995 12.9505 1.24997 12.052 1.25ZM15.2479 6.00188C15.2434 5.15523 15.229 4.24407 15.1835 3.9054C15.1214 3.44393 15.0142 3.24644 14.8839 3.11612C14.7536 2.9858 14.5561 2.87858 14.0946 2.81654C13.6116 2.7516 12.964 2.75 12 2.75C11.036 2.75 10.3884 2.7516 9.90539 2.81654C9.44393 2.87858 9.24644 2.9858 9.11612 3.11612C8.9858 3.24644 8.87858 3.44393 8.81654 3.9054C8.771 4.24407 8.75661 5.15523 8.75208 6.00188C9.1435 6 9.55885 6 10 6H14C14.4412 6 14.8565 6 15.2479 6.00188ZM12 9.25C12.4142 9.25 12.75 9.58579 12.75 10V10.0102C13.8388 10.2845 14.75 11.143 14.75 12.3333C14.75 12.7475 14.4142 13.0833 14 13.0833C13.5858 13.0833 13.25 12.7475 13.25 12.3333C13.25 11.9493 12.8242 11.4167 12 11.4167C11.1758 11.4167 10.75 11.9493 10.75 12.3333C10.75 12.7174 11.1758 13.25 12 13.25C13.3849 13.25 14.75 14.2098 14.75 15.6667C14.75 16.857 13.8388 17.7155 12.75 17.9898V18C12.75 18.4142 12.4142 18.75 12 18.75C11.5858 18.75 11.25 18.4142 11.25 18V17.9898C10.1612 17.7155 9.25 16.857 9.25 15.6667C9.25 15.2525 9.58579 14.9167 10 14.9167C10.4142 14.9167 10.75 15.2525 10.75 15.6667C10.75 16.0507 11.1758 16.5833 12 16.5833C12.8242 16.5833 13.25 16.0507 13.25 15.6667C13.25 15.2826 12.8242 14.75 12 14.75C10.6151 14.75 9.25 13.7903 9.25 12.3333C9.25 11.143 10.1612 10.2845 11.25 10.0102V10C11.25 9.58579 11.5858 9.25 12 9.25Z" fill="currentColor" class="text-gray-700 dark:text-gray-300" />
         </svg>

         <span class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Finances</span>
         <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
             <path fill-rule="evenodd"
                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                 clip-rule="evenodd" />
         </svg>
     </a>
     <!-- Dropdown Menu -->
     <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
         class="absolute  z-10 mt-2 w-36 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
         <div class="py-1">
             <a href="{{ route('coa.index') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Chart Of Account</a>
             <a href="{{ route('invoices.company.agents') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Invoices List</a>
                 <a href="{{ route('invoice.salelist') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Sale Invoices</a>
             <a href="{{ route('invoice.create') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Create Invoice</a>
             <a href="{{ route('charges.index') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Manage Charges
             </a>
             <a href="{{ route('accounting.transaction') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Transactions
             </a>
             <a href="{{ route('accounting.index') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Accounting
             </a>
         </div>
     </div>
 </div>

 <!-- company users -->
 <div x-data="{ open: false }" x-cloak class="relative">
     <a @mouseenter="open = true" @mouseleave="open = false"
         class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
         href="#">
         <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="shrink-0">
             <path d="M15.5 7.5C15.5 9.433 13.933 11 12 11C10.067 11 8.5 9.433 8.5 7.5C8.5 5.567 10.067 4 12 4C13.933 4 15.5 5.567 15.5 7.5Z" fill="currentColor" class="text-gray-700 dark:text-gray-300" />
             <path opacity="0.5" d="M19.5 7.5C19.5 8.88071 18.3807 10 17 10C15.6193 10 14.5 8.88071 14.5 7.5C14.5 6.11929 15.6193 5 17 5C18.3807 5 19.5 6.11929 19.5 7.5Z" fill="currentColor" class="text-gray-700 dark:text-gray-300" />
             <path opacity="0.5" d="M4.5 7.5C4.5 8.88071 5.61929 10 7 10C8.38071 10 9.5 8.88071 9.5 7.5C9.5 6.11929 8.38071 5 7 5C5.61929 5 4.5 6.11929 4.5 7.5Z" fill="currentColor" class="text-gray-700 dark:text-gray-300" />
             <path d="M18 16.5C18 18.433 15.3137 20 12 20C8.68629 20 6 18.433 6 16.5C6 14.567 8.68629 13 12 13C15.3137 13 18 14.567 18 16.5Z" fill="currentColor" class="text-gray-700 dark:text-gray-300" />
             <path opacity="0.5" d="M22 16.5C22 17.8807 20.2091 19 18 19C15.7909 19 14 17.8807 14 16.5C14 15.1193 15.7909 14 18 14C20.2091 14 22 15.1193 22 16.5Z" fill="currentColor" class="text-gray-700 dark:text-gray-300" />
             <path opacity="0.5" d="M2 16.5C2 17.8807 3.79086 19 6 19C8.20914 19 10 17.8807 10 16.5C10 15.1193 8.20914 14 6 14C3.79086 14 2 15.1193 2 16.5Z" fill="currentColor" class="text-gray-700 dark:text-gray-300" />
         </svg>

         <span class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Users</span>
         <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
             <path fill-rule="evenodd"
                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                 clip-rule="evenodd" />
         </svg>
     </a>
     <!-- users Dropdown Menu -->
     <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
         class="absolute z-10 mt-2 w-36 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
         <div class="py-1">
             <a href="{{ route('companies.showCreateOptions') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Add New
             </a>
             <a href="{{ route('agents.index') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Agent List
             </a>
             <a href="{{ route('clients.list') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Clients
                 List</a>


         </div>

     </div>

 </div><!-- ./company users -->

 <!-- company branches -->
 <div x-data="{ open: false }" x-cloak class="relative">
     <a @mouseenter="open = true" @mouseleave="open = false"
         class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
         href="#">
         <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="shrink-0">
             <path d="M9 6C9 7.65685 10.3431 9 12 9C13.6569 9 15 7.65685 15 6C15 4.34315 13.6569 3 12 3C10.3431 3 9 4.34315 9 6Z" fill="currentColor" />
             <path d="M2.5 18C2.5 19.6569 3.84315 21 5.5 21C7.15685 21 8.5 19.6569 8.5 18C8.5 16.3431 7.15685 15 5.5 15C3.84315 15 2.5 16.3431 2.5 18Z" fill="currentColor" />
             <path d="M18.5 21C16.8431 21 15.5 19.6569 15.5 18C15.5 16.3431 16.8431 15 18.5 15C20.1569 15 21.5 16.3431 21.5 18C21.5 19.6569 20.1569 21 18.5 21Z" fill="currentColor" />
             <path d="M7.20468 7.56231C7.51523 7.28821 7.54478 6.81426 7.27069 6.5037C6.99659 6.19315 6.52264 6.1636 6.21208 6.43769C4.39676 8.03991 3.25 10.3865 3.25 13C3.25 13.4142 3.58579 13.75 4 13.75C4.41421 13.75 4.75 13.4142 4.75 13C4.75 10.8347 5.69828 8.89187 7.20468 7.56231Z" fill="currentColor" />
             <path d="M17.7879 6.43769C17.4774 6.1636 17.0034 6.19315 16.7293 6.5037C16.4552 6.81426 16.4848 7.28821 16.7953 7.56231C18.3017 8.89187 19.25 10.8347 19.25 13C19.25 13.4142 19.5858 13.75 20 13.75C20.4142 13.75 20.75 13.4142 20.75 13C20.75 10.3865 19.6032 8.03991 17.7879 6.43769Z" fill="currentColor" />
             <path d="M10.1869 20.0217C9.7858 19.9184 9.37692 20.1599 9.27367 20.561C9.17043 20.9622 9.41192 21.3711 9.81306 21.4743C10.5129 21.6544 11.2458 21.75 12 21.75C12.7542 21.75 13.4871 21.6544 14.1869 21.4743C14.5881 21.3711 14.8296 20.9622 14.7263 20.561C14.6231 20.1599 14.2142 19.9184 13.8131 20.0217C13.2344 20.1706 12.627 20.25 12 20.25C11.373 20.25 10.7656 20.1706 10.1869 20.0217Z" fill="currentColor" />
         </svg>
         <span class="pl-3 text-black pl-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Branches</span>
         <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
             <path fill-rule="evenodd"
                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                 clip-rule="evenodd" />
         </svg>
     </a>
     <!-- Dropdown Menu -->
     <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
         class="absolute  z-10 mt-2 w-36 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
         <div class="py-1">

             <a href="{{ route('branches.index') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Branches List</a>

         </div>
     </div>
 </div><!-- ./company branches -->


 <!-- company reports -->
 <div x-data="{ open: false }" x-cloak class="relative">
     <a @mouseenter="open = true" @mouseleave="open = false"
         class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
         href="#">
         <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="shrink-0">
             <path fill-rule="evenodd" clip-rule="evenodd" d="M20.8293 10.7154L20.3116 12.6473C19.7074 14.9024 19.4052 16.0299 18.7203 16.7612C18.1795 17.3386 17.4796 17.7427 16.7092 17.9223C16.6129 17.9448 16.5152 17.9621 16.415 17.9744C15.4999 18.0873 14.3834 17.7881 12.3508 17.2435C10.0957 16.6392 8.96815 16.3371 8.23687 15.6522C7.65945 15.1114 7.25537 14.4115 7.07573 13.641C6.84821 12.6652 7.15033 11.5377 7.75458 9.28263L8.27222 7.35077C8.3591 7.02654 8.43979 6.7254 8.51621 6.44561C8.97128 4.77957 9.27709 3.86298 9.86351 3.23687C10.4043 2.65945 11.1042 2.25537 11.8747 2.07573C12.8504 1.84821 13.978 2.15033 16.2331 2.75458C18.4881 3.35883 19.6157 3.66095 20.347 4.34587C20.9244 4.88668 21.3285 5.58657 21.5081 6.35703C21.7356 7.3328 21.4335 8.46034 20.8293 10.7154ZM11.0524 9.80589C11.1596 9.40579 11.5709 9.16835 11.971 9.27556L16.8006 10.5697C17.2007 10.6769 17.4381 11.0881 17.3309 11.4882C17.2237 11.8883 16.8125 12.1257 16.4124 12.0185L11.5827 10.7244C11.1826 10.6172 10.9452 10.206 11.0524 9.80589ZM10.2756 12.7033C10.3828 12.3032 10.794 12.0658 11.1941 12.173L14.0919 12.9495C14.492 13.0567 14.7294 13.4679 14.6222 13.868C14.515 14.2681 14.1038 14.5056 13.7037 14.3984L10.8059 13.6219C10.4058 13.5147 10.1683 13.1034 10.2756 12.7033Z" fill="currentColor" />
             <path opacity="0.5" d="M16.4149 17.9745C16.2064 18.6128 15.8398 19.1903 15.347 19.6519C14.6157 20.3368 13.4881 20.6389 11.2331 21.2432C8.97798 21.8474 7.85044 22.1496 6.87466 21.922C6.10421 21.7424 5.40432 21.3383 4.86351 20.7609C4.17859 20.0296 3.87647 18.9021 3.27222 16.647L2.75458 14.7152C2.15033 12.4601 1.84821 11.3325 2.07573 10.3568C2.25537 9.5863 2.65945 8.88641 3.23687 8.3456C3.96815 7.66068 5.09569 7.35856 7.35077 6.75431C7.7774 6.64 8.16369 6.53649 8.51621 6.44534C8.51618 6.44545 8.51624 6.44524 8.51621 6.44534C8.43979 6.72513 8.3591 7.02657 8.27222 7.35081L7.75458 9.28266C7.15033 11.5377 6.84821 12.6653 7.07573 13.6411C7.25537 14.4115 7.65945 15.1114 8.23687 15.6522C8.96815 16.3371 10.0957 16.6393 12.3508 17.2435C14.3833 17.7881 15.4999 18.0873 16.4149 17.9745Z" fill="currentColor" />
         </svg>


         <span
             class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Reports</span>

         <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
             <path fill-rule="evenodd"
                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                 clip-rule="evenodd" />
         </svg>
     </a>
     <!-- reports Dropdown Menu -->
     <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
         class="absolute z-10 mt-2 w-36 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
         <div class="py-1">
             <a href="{{ route('reports.summary') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Summary
             </a>
             <a href="{{ route('reports.accsummary') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Account Summary
             </a>
             <a href="{{ route('reports.performance') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Performance
             </a>
             <a href="{{ route('reports.agent') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Agent Reports
             </a>
             <a href="{{ route('reports.client') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Client Reports
             </a>
         </div>
     </div>
 </div><!-- ./company reports -->

 <!-- Settings -->
 <div x-data="{ open: false }" x-cloak class="relative">
     <a @mouseenter="open = true" @mouseleave="open = false"
         class="flex items-center text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700"
         href="#">
         <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
             <path opacity="0.5" fill-rule="evenodd" clip-rule="evenodd" d="M14.2788 2.15224C13.9085 2 13.439 2 12.5 2C11.561 2 11.0915 2 10.7212 2.15224C10.2274 2.35523 9.83509 2.74458 9.63056 3.23463C9.53719 3.45834 9.50065 3.7185 9.48635 4.09799C9.46534 4.65568 9.17716 5.17189 8.69017 5.45093C8.20318 5.72996 7.60864 5.71954 7.11149 5.45876C6.77318 5.2813 6.52789 5.18262 6.28599 5.15102C5.75609 5.08178 5.22018 5.22429 4.79616 5.5472C4.47814 5.78938 4.24339 6.1929 3.7739 6.99993C3.30441 7.80697 3.06967 8.21048 3.01735 8.60491C2.94758 9.1308 3.09118 9.66266 3.41655 10.0835C3.56506 10.2756 3.77377 10.437 4.0977 10.639C4.57391 10.936 4.88032 11.4419 4.88029 12C4.88026 12.5581 4.57386 13.0639 4.0977 13.3608C3.77372 13.5629 3.56497 13.7244 3.41645 13.9165C3.09108 14.3373 2.94749 14.8691 3.01725 15.395C3.06957 15.7894 3.30432 16.193 3.7738 17C4.24329 17.807 4.47804 18.2106 4.79606 18.4527C5.22008 18.7756 5.75599 18.9181 6.28589 18.8489C6.52778 18.8173 6.77305 18.7186 7.11133 18.5412C7.60852 18.2804 8.2031 18.27 8.69012 18.549C9.17714 18.8281 9.46533 19.3443 9.48635 19.9021C9.50065 20.2815 9.53719 20.5417 9.63056 20.7654C9.83509 21.2554 10.2274 21.6448 10.7212 21.8478C11.0915 22 11.561 22 12.5 22C13.439 22 13.9085 22 14.2788 21.8478C14.7726 21.6448 15.1649 21.2554 15.3694 20.7654C15.4628 20.5417 15.4994 20.2815 15.5137 19.902C15.5347 19.3443 15.8228 18.8281 16.3098 18.549C16.7968 18.2699 17.3914 18.2804 17.8886 18.5412C18.2269 18.7186 18.4721 18.8172 18.714 18.8488C19.2439 18.9181 19.7798 18.7756 20.2038 18.4527C20.5219 18.2105 20.7566 17.807 21.2261 16.9999C21.6956 16.1929 21.9303 15.7894 21.9827 15.395C22.0524 14.8691 21.9088 14.3372 21.5835 13.9164C21.4349 13.7243 21.2262 13.5628 20.9022 13.3608C20.4261 13.0639 20.1197 12.558 20.1197 11.9999C20.1197 11.4418 20.4261 10.9361 20.9022 10.6392C21.2263 10.4371 21.435 10.2757 21.5836 10.0835C21.9089 9.66273 22.0525 9.13087 21.9828 8.60497C21.9304 8.21055 21.6957 7.80703 21.2262 7C20.7567 6.19297 20.522 5.78945 20.2039 5.54727C19.7799 5.22436 19.244 5.08185 18.7141 5.15109C18.4722 5.18269 18.2269 5.28136 17.8887 5.4588C17.3915 5.71959 16.7969 5.73002 16.3099 5.45096C15.8229 5.17191 15.5347 4.65566 15.5136 4.09794C15.4993 3.71848 15.4628 3.45833 15.3694 3.23463C15.1649 2.74458 14.7726 2.35523 14.2788 2.15224Z" fill="#1C274C" />
             <path d="M15.5227 12C15.5227 13.6569 14.1694 15 12.4999 15C10.8304 15 9.47705 13.6569 9.47705 12C9.47705 10.3431 10.8304 9 12.4999 9C14.1694 9 15.5227 10.3431 15.5227 12Z" fill="#1C274C" />
         </svg>


         <span
             class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#f3f4f6] dark:group-hover:text-white-dark">Settings</span>

         <svg class="ml-1 h-4 w-4 text-gray-400 dark:text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-200"
             xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
             <path fill-rule="evenodd"
                 d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                 clip-rule="evenodd" />
         </svg>
     </a>
     <!-- Tasks Dropdown Menu -->
     <div x-show="open" @mouseenter="open = true" @mouseleave="open = false"
         class="absolute z-10 mt-2 w-36 bg-white dark:bg-gray-800 rounded-md shadow-lg ring-1 ring-black ring-opacity-5"
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95">
         <div class="py-1">
             <a href="{{ route('agentsetting') }}"
                 class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">
                 Add Agent Type
             </a>

         </div>
     </div>
 </div>