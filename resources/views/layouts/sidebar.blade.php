<nav x-cloak :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    class="sidebar fixed bottom-0 top-0 z-50 h-full min-h-screen w-[260px] shadow-[5px_0_25px_0_rgba(94,92,154,0.1)] transition-all duration-300">

    <div class="h-full bg-white dark:bg-[#000]">

        <div class="flex items-center justify-between px-4 py-3 mb-5">
            <a href="index.html" class="main-logo flex shrink-0 items-center">
                <img class="ml-[5px] w-12 flex-none pr-2" src="{{ asset('images/City0logo.svg') }}" alt="image">
                <span
                    class="align-middle text-2xl font-semibold ltr:ml-1.5 rtl:mr-1.5 dark:text-white-light lg:inline">City
                    App</span>
            </a>
            <button @click="sidebarOpen = false"
                class="flex h-8 w-8 items-center justify-center rounded-full transition duration-300 hover:bg-gray-500/10 dark:text-white dark:hover:bg-dark-light/10">
                <svg class="h-5 w-5" width="20" height="20" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M13 19L7 12L13 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                        stroke-linejoin="round" />
                    <path opacity="0.5" d="M17 19L11 12L17 5" stroke="currentColor" stroke-width="1.5"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

        </div>


        <!-- sidebar items -->

        <ul class="perfect-scrollbar relative h-[calc(100vh-80px)] space-y-0.5 overflow-y-auto overflow-x-hidden p-4 py-0 font-semibold ps ps--active-y"
            x-data="{ activeDropdown: 'dashboard', activeSubmenu: null }">
            <li class="menu nav-item">
                <button type="button" class="nav-link group" :class="{'active' : activeDropdown === 'dashboard'}"
                    @click="activeDropdown === 'dashboard' ? activeDropdown = null : activeDropdown = 'dashboard'">
                    <div class="flex items-center">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="3" width="8" height="8" fill="#1C274C" />
                        <rect x="13" y="3" width="8" height="4" fill="#1C274C" />
                        <rect x="13" y="9" width="8" height="8" fill="#1C274C" />
                        <rect x="3" y="13" width="8" height="4" fill="#1C274C" />
                    </svg>

                        <span class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#506690] dark:group-hover:text-white-dark">Dashboard</span>
                    </div>
                    <div class="rtl:rotate-180" :class="{'!rotate-90' : activeDropdown === 'dashboard'}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg>
                    </div>
                </button>

                <!-- Dashboard dropdown -->
                <ul x-show="activeDropdown === 'dashboard'" x-collapse="" class="sub-menu text-gray-500" style="height: auto;">
                    <li>
                        <a href="{{ route('dashboard') }}">Revenue</a>
                    </li>
                    <li>
                        <a href="#">Logout</a>
                    </li>
                </ul>
            </li>

            <li class="menu nav-item">
                <button type="button" class="nav-link group" :class="{'active' : activeDropdown === 'companies'}"
                    @click="activeDropdown === 'companies' ? activeDropdown = null : activeDropdown = 'companies'">
                    <div class="flex items-center">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="7" width="5" height="13" rx="1" fill="#1C274C" />
                        <rect x="10" y="3" width="6" height="17" rx="1" fill="#1C274C" />
                        <rect x="18" y="10" width="3" height="10" rx="1" fill="#1C274C" />
                        <rect x="4" y="8" width="3" height="2" fill="#FFFFFF" />
                        <rect x="11" y="5" width="3" height="2" fill="#FFFFFF" />
                        <rect x="11" y="9" width="3" height="2" fill="#FFFFFF" />
                        <rect x="11" y="13" width="3" height="2" fill="#FFFFFF" />
                        <rect x="19" y="11" width="1" height="2" fill="#FFFFFF" />
                    </svg>
                     <span
                            class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#506690] dark:group-hover:text-white-dark">Companies</span>
                    </div>
                    <div class="rtl:rotate-180" :class="{'!rotate-90' : activeDropdown === 'companies'}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg>
                    </div>
                </button>
                <ul x-show="activeDropdown === 'companies'" x-collapse="" class="sub-menu text-gray-500">

                    <li>
                        <a href="{{ route('companiesnew.new') }}"
                            class="{{ request()->is('companiesnew') ? 'active' : '' }}">Add Company</a>
                    </li>
                    <li>

                        <a href="{{ route('companies.index') }}"
                            class="{{ request()->is('companies.index') ? 'active' : '' }}">Companies List</a>
                    </li>
                </ul>
            </li>

            <li class="menu nav-item">
                <button type="button" class="nav-link group" :class="{'active' : activeDropdown === 'agents'}"
                    @click="activeDropdown === 'agents' ? activeDropdown = null : activeDropdown = 'agents'">
                    <div class="flex items-center">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="8" r="4" fill="#1C274C" />
                        <path d="M18 14C18 15.1046 17.1046 16 16 16H8C6.89543 16 6 15.1046 6 14V13C6 11.8954 6.89543 11 8 11H16C17.1046 11 18 11.8954 18 13V14Z" fill="#1C274C" />
                        <path opacity="0.5" d="M20 12.5C20 13.8807 18.8807 15 17.5 15C16.1193 15 15 13.8807 15 12.5C15 11.1193 16.1193 10 17.5 10C18.8807 10 20 11.1193 20 12.5Z" fill="#1C274C" />
                        <path opacity="0.5" d="M9 12.5C9 13.8807 7.88071 15 6.5 15C5.11929 15 4 13.8807 4 12.5C4 11.1193 5.11929 10 6.5 10C7.88071 10 9 11.1193 9 12.5Z" fill="#1C274C" />
                        <path d="M18 16.5C18 18.433 15.3137 20 12 20C8.68629 20 6 18.433 6 16.5C6 15.1193 7.11929 14 8.5 14H15.5C16.8807 14 18 15.1193 18 16.5Z" fill="#1C274C" />
                    </svg>
                  <span
                            class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#506690] dark:group-hover:text-white-dark">Agents</span>
                    </div>
                    <div class="rtl:rotate-180" :class="{'!rotate-90' : activeDropdown === 'agents'}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg>
                    </div>
                </button>
                <ul x-show="activeDropdown === 'agents'" x-collapse="" class="sub-menu text-gray-500">

                            <li>
                                <a href="{{ route('agents.index') }}" class="{{ request()->is('agents') ? 'active' : '' }}">Agents List</a>
                            </li>
                            <li>
                                <a href="{{ route('agentsnew.new') }}" class="{{ request()->is('agentsnew') ? 'active' : '' }}">Agent New</a>
                            </li>
                            <li>
                                <a href="{{ route('agentsupload.upload') }}" class="{{ request()->is('agentsupload') ? 'active' : '' }}">Agents Upload</a>
                            </li>
                </ul>
            </li>

            <li class="menu nav-item">
                <button type="button" class="nav-link group" :class="{'active' : activeDropdown === 'tasks'}"
                    @click="activeDropdown === 'tasks' ? activeDropdown = null : activeDropdown = 'tasks'">
                    <div class="flex items-center">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2" fill="#1C274C" />
                        <path d="M7 7H13V9H7V7Z" fill="#F9D923" />
                        <path d="M7 11H17V13H7V11Z" fill="#F9D923" />
                        <path d="M7 15H17V17H7V15Z" fill="#F9D923" />
                        <path d="M17.707 8.293L15.707 10.293L14.707 9.293L13.293 10.707L15.707 13.121L18.121 10.707L17.707 8.293Z" fill="#F9D923" />
                    </svg>
                    <span
                            class="pl-3 text-black ltr:pl-3 rtl:pr-3 dark:text-[#506690] dark:group-hover:text-white-dark">Tasks</span>
                    </div>
                    <div class="rtl:rotate-180" :class="{'!rotate-90' : activeDropdown === 'tasks'}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 5L15 12L9 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg>
                    </div>
                </button>
                <ul x-show="activeDropdown === 'tasks'" x-collapse="" class="sub-menu text-gray-500">
                            <li>
                                <a href="{{ route('tasks.index') }}" class="{{ request()->is('tasks') ? 'active' : '' }}">Task List</a>
                            </li>
                            <li>
                                <a href="{{ route('tasksupload.upload') }}" class="{{ request()->is('tasksupload') ? 'active' : '' }}">Tasks Upload</a>
                            </li>
                </ul>
            </li>

        </ul>
   
    </div>
</nav>