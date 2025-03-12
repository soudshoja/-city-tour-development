<x-app-layout>
    <div class="grid grid-cols-1">
        <div class="panel h-full overflow-hidden border-0 p-0">
            <div class="bg-gradient-to-r from-[#4361ee] to-[#160f6b] p-6">
                <div class="mb-6 flex items-center">
                    <div
                        class="w-full justify-between flex items-center rounded-full bg-black/50 p-1 font-semibold text-white pr-3">
                        <div class="flex items-center">
                            <x-application-logo
                                class="block h-8 w-8 rounded-full border-2 border-white/50 object-cover mr-1" />
                            <h3 class="px-2">{{ Auth::user()->name }}</h3>
                        </div>
                        <div>
                            <div data-tooltip="Reload"
                                class="rotate refresh-icon relative w-12 h-12 flex items-center justify-center bg-[#b1c0db] dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-full shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                    viewBox="0 0 24 24">
                                    <path
                                        d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25">
                                    </path>
                                    <path
                                        d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z"
                                        opacity=".5"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar with tabs -->
            <div class="bg-white p-5 grid grid-cols-6 gap-3" x-data="{ tab: 'Account' }">
                <div class="rounded-lg p-4">
                    <a href="javascript:void(0)" @click="tab = 'Account'">
                        <div class="flex items-center space-x-2 bg-gray-100 rounded-lg px-4 py-3"
                            :class="{ 'text-primary bg-gray-200': tab === 'Account' }">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">
                                <path fill="currentColor"
                                    d="M10 0c5.523 0 10 4.477 10 10s-4.477 10-10 10S0 15.523 0 10S4.477 0 10 0m-.145 7.21a.7.7 0 0 0-.698.697v7.558a.698.698 0 0 0 1.395 0V7.907a.7.7 0 0 0-.697-.698m.028-2.791a.93.93 0 1 0 0 1.86a.93.93 0 0 0 0-1.86" />
                            </svg>
                            <span class="text-sm inline-block">Account Information</span>
                        </div>
                    </a>

                    <ul class="mt-4 space-y-4">
                        <li>
                            <a href="javascript:void(0)" @click="tab = 'Payment'"
                                class="flex items-center space-x-4 px-4 py-3 rounded-lg text-gray-700 bg-gray-100"
                                :class="{ 'text-primary bg-gray-200': tab === 'Payment' }">

                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                    viewBox="0 0 14 14">
                                    <g fill="none" stroke="currentColor" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path
                                            d="M12.91 5.5H1.09c-.56 0-.8-.61-.36-.9L6.64.73a.71.71 0 0 1 .72 0l5.91 3.87c.44.29.2.9-.36.9Z" />
                                        <rect width="13" height="2.5" x=".5" y="11" rx=".5" />
                                        <path d="M2 5.5V11m2.5-5.5V11M7 5.5V11m2.5-5.5V11M12 5.5V11" />
                                    </g>
                                </svg>

                                <span class="text-sm">Payment</span>
                            </a>
                        </li>


                        <li>
                            <a href="javascript:void(0)" @click="tab = 'Invoices'"
                                class="flex items-center space-x-4 px-4 py-3 rounded-lg text-gray-700 bg-gray-100 transition-all duration-200 ease-in-out"
                                :class="{ 'text-primary bg-gray-200': tab === 'Invoices' }">

                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                    viewBox="0 0 24 24">
                                    <path fill="currentColor"
                                        d="M6 21q-.846 0-1.423-.577T4 19v-1.192q0-.349.23-.578t.578-.23H7V3.773q0-.137.102-.18t.208.024l.744.494q.112.073.215.073t.216-.073l.877-.569q.111-.073.215-.073t.215.073l.877.57q.112.072.216.072t.215-.073l.877-.569q.112-.073.215-.073q.104 0 .216.073l.877.57q.111.072.215.072t.216-.073l.876-.569q.112-.073.216-.073t.215.073l.877.57q.112.073.216.073q.103 0 .215-.073l.877-.57q.111-.073.215-.073t.216.073l.877.57q.111.073.215.073t.215-.073l.744-.495q.106-.067.208-.024t.102.18V19q0 .846-.577 1.423T18 21zm12-1q.425 0 .713-.288T19 19V5H8v12h8.192q.349 0 .578.23t.23.578V19q0 .425.288.713T18 20M9.885 7.5h4.346q.213 0 .357.143T14.73 8t-.143.357t-.357.143H9.885q-.214 0-.357-.143q-.144-.143-.144-.357t.144-.357t.357-.143m0 3h4.346q.213 0 .357.143t.143.357t-.143.357t-.357.143H9.885q-.214 0-.357-.143q-.144-.143-.144-.357t.144-.357t.357-.143m7-1.73q-.31 0-.54-.23t-.23-.54t.23-.54t.54-.23t.539.23t.23.54t-.23.54t-.54.23m0 3q-.309 0-.539-.23t-.23-.54t.23-.54t.54-.23t.539.23t.23.54t-.23.54t-.54.23M6 20h10v-2H5v1q0 .425.288.713T6 20m-1 0v-2z" />
                                </svg>

                                <span class="text-sm">Invoices</span>
                            </a>
                        </li>

                        <li>
                            <a href="javascript:void(0)" @click="tab = 'Orders'"
                                class="flex items-center space-x-4 px-4 py-3 rounded-lg text-gray-700 bg-gray-100 transition-all duration-200 ease-in-out"
                                :class="{ 'text-primary bg-gray-200': tab === 'Orders' }">

                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                    viewBox="0 0 24 24">
                                    <path fill="currentColor"
                                        d="M6 13c-2.21 0-4 1.79-4 4s1.79 4 4 4s4-1.79 4-4s-1.79-4-4-4m0 6c-1.1 0-2-.9-2-2s.9-2 2-2s2 .9 2 2s-.9 2-2 2M6 3C3.79 3 2 4.79 2 7s1.79 4 4 4s4-1.79 4-4s-1.79-4-4-4m6 2h10v2H12zm0 14v-2h10v2zm0-8h10v2H12z" />
                                </svg>

                                <span class="text-sm">Orders</span>
                            </a>
                        </li>


                        <li>
                            <a href="javascript:void(0)" @click="tab = 'Security'"
                                class="flex items-center space-x-4 px-4 py-3 rounded-lg text-gray-700 bg-gray-100 transition-all duration-200 ease-in-out"
                                :class="{ 'text-primary bg-gray-200': tab === 'Security' }">

                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                    viewBox="0 0 24 24">
                                    <path fill="none" stroke="currentColor" stroke-linecap="round"
                                        stroke-linejoin="round" stroke-width="1.5"
                                        d="M11.998 2C8.99 2 7.04 4.019 4.734 4.755c-.938.3-1.407.449-1.597.66c-.19.21-.245.519-.356 1.135c-1.19 6.596 1.41 12.694 7.61 15.068c.665.255.998.382 1.61.382s.946-.128 1.612-.383c6.199-2.373 8.796-8.471 7.606-15.067c-.111-.616-.167-.925-.357-1.136s-.658-.36-1.596-.659C16.959 4.019 15.006 2 11.998 2M12 7v2"
                                        color="currentColor" />
                                </svg>

                                <span class="text-sm">Security</span>
                            </a>
                        </li>


                        <li>
                            <a href="javascript:void(0)" @click="tab = 'Documentation'"
                                class="flex items-center space-x-4 px-4 py-3 rounded-lg text-gray-700 bg-gray-100 transition-all duration-200 ease-in-out"
                                :class="{ 'text-primary bg-gray-200': tab === 'Documentation' }">

                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                    viewBox="0 0 2048 2048">
                                    <path fill="currentColor"
                                        d="M1920 512v1408H768v-256H512v-256H256V0h731l256 256h421v256zm-896-128h165l-165-165zm256 896V512H896V128H384v1152zm256 256V384h-128v1024H640v128zm257-896h-129v1024H896v128h897z" />
                                </svg>

                                <span class="text-sm">Documentation</span>
                            </a>
                        </li>

                    </ul>
                </div>

                <!-- Content Section -->
                <div class="col-span-5">
                    <div x-show="tab === 'Account'">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                    <div x-show="tab === 'Payment'">
                        <p>This is the payment section.</p>
                    </div>
                    <div x-show="tab === 'Invoices'">
                        <p>This is the invoices section.</p>
                    </div>
                    <div x-show="tab === 'Orders'">
                        <p>This is the orders section.</p>
                    </div>
                    <div x-show="tab === 'Security'">
                        <p>This is the security section.</p>
                    </div>
                    <div x-show="tab === 'Documentation'">
                        <p>This is the documentation section.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
