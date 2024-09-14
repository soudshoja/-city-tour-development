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
                        <svg class="shrink-0 group-hover:!text-primary" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path opacity="0.5"
                                d="M2 12.2039C2 9.91549 2 8.77128 2.5192 7.82274C3.0384 6.87421 3.98695 6.28551 5.88403 5.10813L7.88403 3.86687C9.88939 2.62229 10.8921 2 12 2C13.1079 2 14.1106 2.62229 16.116 3.86687L18.116 5.10812C20.0131 6.28551 20.9616 6.87421 21.4808 7.82274C22 8.77128 22 9.91549 22 12.2039V13.725C22 17.6258 22 19.5763 20.8284 20.7881C19.6569 22 17.7712 22 14 22H10C6.22876 22 4.34315 22 3.17157 20.7881C2 19.5763 2 17.6258 2 13.725V12.2039Z"
                                fill="currentColor"></path>
                            <path
                                d="M9 17.25C8.58579 17.25 8.25 17.5858 8.25 18C8.25 18.4142 8.58579 18.75 9 18.75H15C15.4142 18.75 15.75 18.4142 15.75 18C15.75 17.5858 15.4142 17.25 15 17.25H9Z"
                                fill="currentColor"></path>
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
                      <!-- Agents Parent Item -->
                      <li>
                        <a href="#" @click.prevent="activeSubmenu = activeSubmenu === 'companies' ? null : 'companies'"
                            class="cursor-pointer">
                            Companies
                            <!-- Dropdown Indicator -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                stroke="currentColor" class="w-4 h-4 inline">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </a>
                        <!-- Sub-Items for Agents -->
                        <ul x-show="activeSubmenu === 'companies'" x-collapse="" class="sub-menu pl-4">
                            <li>
                                <a href="{{ route('companies.index') }}" class="{{ request()->is('companies') ? 'active' : '' }}">Companies List</a>
                            </li>
                            <li>
                                <a href="{{ route('companiesnew.new') }}" class="{{ request()->is('companiesnew') ? 'active' : '' }}">Companies New</a>
                            </li>
                            <li>
                                <a href="{{ route('companiesupload.upload') }}" class="{{ request()->is('companiesupload') ? 'active' : '' }}">Companies Upload</a>
                            </li>
                        </ul>
                    </li>
                    <!-- Agents Parent Item -->
                    <li>
                        <a href="#" @click.prevent="activeSubmenu = activeSubmenu === 'agents' ? null : 'agents'"
                            class="cursor-pointer">
                            Agents
                            <!-- Dropdown Indicator -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                stroke="currentColor" class="w-4 h-4 inline">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </a>
                        <!-- Sub-Items for Agents -->
                        <ul x-show="activeSubmenu === 'agents'" x-collapse="" class="sub-menu pl-4">
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
                   <!-- Agents Parent Item -->
                   <li>
                        <a href="#" @click.prevent="activeSubmenu = activeSubmenu === 'tasks' ? null : 'tasks'"
                            class="cursor-pointer">
                            Task
                            <!-- Dropdown Indicator -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                stroke="currentColor" class="w-4 h-4 inline">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </a>
                        <!-- Sub-Items for Agents -->
                        <ul x-show="activeSubmenu === 'tasks'" x-collapse="" class="sub-menu pl-4">
                            <li>
                                <a href="{{ route('tasks.index') }}" class="{{ request()->is('tasks') ? 'active' : '' }}">Task List</a>
                            </li>
                            <li>
                                <a href="{{ route('tasksupload.upload') }}" class="{{ request()->is('tasksupload') ? 'active' : '' }}">Tasks Upload</a>
                            </li>
                        </ul>
                    </li>

                    <li>
                        <a href="#">Finance</a>
                    </li>
                    <li>
                        <a href="#">Crypto</a>
                    </li>
                </ul>
            </li>
        </ul>

    </div>
</nav>