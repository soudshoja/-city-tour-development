<x-app-layout>
    <style>
        svg:hover path {
            fill: blue;
        }
    </style>
    <div>
        <!-- Breadcrumbs -->
        <ul class="flex space-x-2 rtl:space-x-reverse pb-5 text-base md:text-lg sm:text-sm">
            <li>
                <a href="{{ route('dashboard') }}" class="customBlueColor hover:underline">Dashboard</a>
            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <a href="{{ route('clients.index') }}" class="customBlueColor hover:underline">Clients List</a>

            </li>
            <li class="before:content-['/'] before:mr-1 ">
                <span>{{ $client->first_name }} </span>
            </li>
        </ul>
        <!-- ./Breadcrumbs -->

        <!-- details secion -->
        <div class="sm:flex gap-2">
            <!-- Agents Overview -->
            <div class="panel w-[100%] md:w-[75%]">
                <div class="mb-5 flex justify-between">
                    <h5 class="text-lg font-semibold dark:text-white-light">
                        <span class="customBlueColor">Tasks</span> List
                    </h5>
                    <!-- add an icon here -->
                </div>
                <!-- tasks Section -->
                <div class="mt-5 overflow-x-auto">
                    <div class="max-h-96 overflow-y-auto custom-scrollbar">
                        <!-- Client Orders Section -->
                        @if ($tasks->count() > 0)
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Cancellation Policy</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            @foreach ($tasks as $task)
                            <tbody>
                                <tr>
                                    <td> {{ $task->reference }}-{{ $task->additional_info }} {{ $task->venue }}
                                    </td>
                                    @if (is_array($task->cancellation_policy) && !empty($task->cancellation_policy))
                                    <td class="grid">
                                        @foreach ($task->cancellation_policy as  $policy)
                                        <div class="p-1 m-1 border rounded">
                                            @foreach ($policy as $key => $value)
                                            <p>{{ $key }}: {{ $value }}</p>
                                            @endforeach
                                        </div>
                                        @endforeach
                                    </td>
                                    @else
                                    <td>
                                        {{ $task->cancellation_policy }}
                                    </td>
                                    @endif
                                    <td> {{ $task->status }} </td>
                                    <td x-data='{ open: false }'>
                                        <div @click="open = !open" class="cursor-pointer">
                                            <svg width="24" height="24" viewBox="0 0 24 24"
                                                fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M11.9426 1.25L13.5 1.25C13.9142 1.25 14.25 1.58579 14.25 2C14.25 2.41421 13.9142 2.75 13.5 2.75H12C9.62177 2.75 7.91356 2.75159 6.61358 2.92637C5.33517 3.09825 4.56445 3.42514 3.9948 3.9948C3.42514 4.56445 3.09825 5.33517 2.92637 6.61358C2.75159 7.91356 2.75 9.62177 2.75 12C2.75 14.3782 2.75159 16.0864 2.92637 17.3864C3.09825 18.6648 3.42514 19.4355 3.9948 20.0052C4.56445 20.5749 5.33517 20.9018 6.61358 21.0736C7.91356 21.2484 9.62177 21.25 12 21.25C14.3782 21.25 16.0864 21.2484 17.3864 21.0736C18.6648 20.9018 19.4355 20.5749 20.0052 20.0052C20.5749 19.4355 20.9018 18.6648 21.0736 17.3864C21.2484 16.0864 21.25 14.3782 21.25 12V10.5C21.25 10.0858 21.5858 9.75 22 9.75C22.4142 9.75 22.75 10.0858 22.75 10.5V12.0574C22.75 14.3658 22.75 16.1748 22.5603 17.5863C22.366 19.031 21.9607 20.1711 21.0659 21.0659C20.1711 21.9607 19.031 22.366 17.5863 22.5603C16.1748 22.75 14.3658 22.75 12.0574 22.75H11.9426C9.63423 22.75 7.82519 22.75 6.41371 22.5603C4.96897 22.366 3.82895 21.9607 2.93414 21.0659C2.03933 20.1711 1.63399 19.031 1.43975 17.5863C1.24998 16.1748 1.24999 14.3658 1.25 12.0574V11.9426C1.24999 9.63423 1.24998 7.82519 1.43975 6.41371C1.63399 4.96897 2.03933 3.82895 2.93414 2.93414C3.82895 2.03933 4.96897 1.63399 6.41371 1.43975C7.82519 1.24998 9.63423 1.24999 11.9426 1.25ZM16.7705 2.27592C18.1384 0.908029 20.3562 0.908029 21.7241 2.27592C23.092 3.6438 23.092 5.86158 21.7241 7.22947L15.076 13.8776C14.7047 14.2489 14.4721 14.4815 14.2126 14.684C13.9069 14.9224 13.5761 15.1268 13.2261 15.2936C12.929 15.4352 12.6169 15.5392 12.1188 15.7052L9.21426 16.6734C8.67801 16.8521 8.0868 16.7126 7.68711 16.3129C7.28742 15.9132 7.14785 15.322 7.3266 14.7857L8.29477 11.8812C8.46079 11.3831 8.56479 11.071 8.7064 10.7739C8.87319 10.4239 9.07761 10.0931 9.31605 9.78742C9.51849 9.52787 9.7511 9.29529 10.1224 8.924L16.7705 2.27592ZM20.6634 3.33658C19.8813 2.55448 18.6133 2.55448 17.8312 3.33658L17.4546 3.7132C17.4773 3.80906 17.509 3.92327 17.5532 4.05066C17.6965 4.46372 17.9677 5.00771 18.48 5.51999C18.9923 6.03227 19.5363 6.30346 19.9493 6.44677C20.0767 6.49097 20.1909 6.52273 20.2868 6.54543L20.6634 6.16881C21.4455 5.38671 21.4455 4.11867 20.6634 3.33658ZM19.1051 7.72709C18.5892 7.50519 17.9882 7.14946 17.4193 6.58065C16.8505 6.01185 16.4948 5.41082 16.2729 4.89486L11.2175 9.95026C10.801 10.3668 10.6376 10.532 10.4988 10.7099C10.3274 10.9297 10.1804 11.1676 10.0605 11.4192C9.96337 11.623 9.88868 11.8429 9.7024 12.4017L9.27051 13.6974L10.3026 14.7295L11.5983 14.2976C12.1571 14.1113 12.377 14.0366 12.5808 13.9395C12.8324 13.8196 13.0703 13.6726 13.2901 13.5012C13.468 13.3624 13.6332 13.199 14.0497 12.7825L19.1051 7.72709Z"
                                                    fill="#1C274C" />
                                            </svg>
                                        </div>
                                        <div x-cloak x-show="open"
                                            class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-10">
                                            <div @click.away="open = false"
                                                class="bg-white rounded-md p-4 w-96 h-auto">
                                                <div
                                                    class="header bg-gray-400 text-white rounded-sm shadow-md my-2 p-2">
                                                    <h3 class="">Edit Task</h3>
                                                </div>
                                                <form action="{{ route('tasks.update', $task->id) }}"
                                                    method="POST" class="grid gap-4">
                                                    @method('PUT')
                                                    @csrf
                                                    <input type="hidden" name="id"
                                                        value="{{ $task->id }}">
                                                    <input type="text" name="reference"
                                                        value="{{ $task->reference }}"
                                                        class="border border-gray-200 dark:border-gray-600 p-2 rounded-md">
                                                    <input type="text" name="additional_info"
                                                        value="{{ $task->additional_info }}"
                                                        class="border border-gray-200 dark:border-gray-600 p-2 rounded-md">
                                                    <input type="text" name="venue"
                                                        value="{{ $task->venue }}"
                                                        class="border border-gray-200 dark:border-gray-600 p-2 rounded-md">
                                                    <select name="status" id="" name="status"
                                                        class="border border-gray-200 dark:border-gray-600 p-2 rounded-md">
                                                        <option value="pending">Pending</option>
                                                        <option value="completed">Completed</option>
                                                        <option value="cancelled">Cancelled</option>
                                                    </select>
                                                    <button type="submit"
                                                        class="p-2 rounded-md bg-black text-white">Update</button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            @endforeach
                        </table>
                        @else
                        <p class="text-gray-500 dark:text-gray-400">No tasks found for this client.</p>
                        @endif

                    </div>
                </div>
            </div>

            <div class="panel h-full overflow-hidden border-0 p-0 mt-2 sm:mt-0">

                <div class="bg-gradient-to-r from-[#4361ee] to-[#160f6b] p-6">
                    <div class="mb-6 flex items-center justify-between">
                        <div class="flex items-center rounded-full bg-black/50 p-1 font-semibold text-white  ">
                            <x-application-logo
                                class="block h-8 w-8 rounded-full border-2 border-white/50 object-cover ltr:mr-1 rtl:ml-1" />
                            <h3 class="px-2">{{ $client->first_name }}</h3>
                            @if ($balanceCredit > 0)
                            <div x-data="{ clientCreditRefund: false }" class="flex items-center">
                                <button @click="clientCreditRefund = true"
                                    class="bg-white hover:bg-gray-200 text-black p-2 rounded-full cursor-pointer">
                                    {{ $balanceCredit }} KWD
                                </button>
                                <div class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-10"
                                    x-show="clientCreditRefund" x-cloak>
                                    <div @click.away="clientCreditRefund = false"
                                        class="bg-white rounded-md p-4 w-96 h-auto">
                                        <div class="header bg-gray-400 text-white rounded-sm shadow-md my-2 p-2">
                                            <h3 class="">Refund Client Credit</h3>
                                        </div>
                                        <form action="{{ route('clients.refund', $client->id) }}" method="POST"
                                            class="grid gap-4">
                                            @csrf
                                            @if ($agents->count() > 1)
                                            <select name="agent_id" id="agent_id"
                                                class="border border-gray-200 dark:border-gray-600 p-2 rounded-md">
                                                @foreach ($agents as $agent)
                                                <option value="{{ $agent->id }}">{{ $agent->name }}
                                                </option>
                                                @endforeach
                                            </select>
                                            @else
                                            <input type="hidden" name="agent_id" value="{{ $agents[0]->id }}">
                                            @endif
                                            <input type="number" name="amount" min="0" step="0.01"
                                                max="{{ $balanceCredit }}" placeholder="Enter refund amount"
                                                class="border border-gray-200 dark:border-gray-600 p-2 rounded-md text-black">
                                            <button type="submit" class="p-2 rounded-md bg-black text-white"
                                                {{ $balanceCredit == 0 ? 'disabled' : '' }}>
                                                Refund
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @else
                            <div class="bg-white text-black p-2 rounded-full ">
                                {{ $balanceCredit }} KWD
                            </div>
                            @endif
                        </div>
                        <button type="button" onclick="EditClientDetails()"
                            class="flex h-9 w-9 items-center justify-between rounded-md bg-black text-white hover:opacity-80 ltr:ml-auto rtl:mr-auto">
                            <svg class="m-auto h-6 w-6" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M12 8.25C9.92894 8.25 8.25 9.92893 8.25 12C8.25 14.0711 9.92894 15.75 12 15.75C14.0711 15.75 15.75 14.0711 15.75 12C15.75 9.92893 14.0711 8.25 12 8.25ZM9.75 12C9.75 10.7574 10.7574 9.75 12 9.75C13.2426 9.75 14.25 10.7574 14.25 12C14.25 13.2426 13.2426 14.25 12 14.25C10.7574 14.25 9.75 13.2426 9.75 12Z"
                                    fill="#F5F5F5" />
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M11.9747 1.25C11.5303 1.24999 11.1592 1.24999 10.8546 1.27077C10.5375 1.29241 10.238 1.33905 9.94761 1.45933C9.27379 1.73844 8.73843 2.27379 8.45932 2.94762C8.31402 3.29842 8.27467 3.66812 8.25964 4.06996C8.24756 4.39299 8.08454 4.66251 7.84395 4.80141C7.60337 4.94031 7.28845 4.94673 7.00266 4.79568C6.64714 4.60777 6.30729 4.45699 5.93083 4.40743C5.20773 4.31223 4.47642 4.50819 3.89779 4.95219C3.64843 5.14353 3.45827 5.3796 3.28099 5.6434C3.11068 5.89681 2.92517 6.21815 2.70294 6.60307L2.67769 6.64681C2.45545 7.03172 2.26993 7.35304 2.13562 7.62723C1.99581 7.91267 1.88644 8.19539 1.84541 8.50701C1.75021 9.23012 1.94617 9.96142 2.39016 10.5401C2.62128 10.8412 2.92173 11.0602 3.26217 11.2741C3.53595 11.4461 3.68788 11.7221 3.68786 12C3.68785 12.2778 3.53592 12.5538 3.26217 12.7258C2.92169 12.9397 2.62121 13.1587 2.39007 13.4599C1.94607 14.0385 1.75012 14.7698 1.84531 15.4929C1.88634 15.8045 1.99571 16.0873 2.13552 16.3727C2.26983 16.6469 2.45535 16.9682 2.67758 17.3531L2.70284 17.3969C2.92507 17.7818 3.11058 18.1031 3.28089 18.3565C3.45817 18.6203 3.64833 18.8564 3.89769 19.0477C4.47632 19.4917 5.20763 19.6877 5.93073 19.5925C6.30717 19.5429 6.647 19.3922 7.0025 19.2043C7.28833 19.0532 7.60329 19.0596 7.8439 19.1986C8.08452 19.3375 8.24756 19.607 8.25964 19.9301C8.27467 20.3319 8.31403 20.7016 8.45932 21.0524C8.73843 21.7262 9.27379 22.2616 9.94761 22.5407C10.238 22.661 10.5375 22.7076 10.8546 22.7292C11.1592 22.75 11.5303 22.75 11.9747 22.75H12.0252C12.4697 22.75 12.8407 22.75 13.1454 22.7292C13.4625 22.7076 13.762 22.661 14.0524 22.5407C14.7262 22.2616 15.2616 21.7262 15.5407 21.0524C15.686 20.7016 15.7253 20.3319 15.7403 19.93C15.7524 19.607 15.9154 19.3375 16.156 19.1985C16.3966 19.0596 16.7116 19.0532 16.9974 19.2042C17.3529 19.3921 17.6927 19.5429 18.0692 19.5924C18.7923 19.6876 19.5236 19.4917 20.1022 19.0477C20.3516 18.8563 20.5417 18.6203 20.719 18.3565C20.8893 18.1031 21.0748 17.7818 21.297 17.3969L21.3223 17.3531C21.5445 16.9682 21.7301 16.6468 21.8644 16.3726C22.0042 16.0872 22.1135 15.8045 22.1546 15.4929C22.2498 14.7697 22.0538 14.0384 21.6098 13.4598C21.3787 13.1586 21.0782 12.9397 20.7378 12.7258C20.464 12.5538 20.3121 12.2778 20.3121 11.9999C20.3121 11.7221 20.464 11.4462 20.7377 11.2742C21.0783 11.0603 21.3788 10.8414 21.6099 10.5401C22.0539 9.96149 22.2499 9.23019 22.1547 8.50708C22.1136 8.19546 22.0043 7.91274 21.8645 7.6273C21.7302 7.35313 21.5447 7.03183 21.3224 6.64695L21.2972 6.60318C21.0749 6.21825 20.8894 5.89688 20.7191 5.64347C20.5418 5.37967 20.3517 5.1436 20.1023 4.95225C19.5237 4.50826 18.7924 4.3123 18.0692 4.4075C17.6928 4.45706 17.353 4.60782 16.9975 4.79572C16.7117 4.94679 16.3967 4.94036 16.1561 4.80144C15.9155 4.66253 15.7524 4.39297 15.7403 4.06991C15.7253 3.66808 15.686 3.2984 15.5407 2.94762C15.2616 2.27379 14.7262 1.73844 14.0524 1.45933C13.762 1.33905 13.4625 1.29241 13.1454 1.27077C12.8407 1.24999 12.4697 1.24999 12.0252 1.25H11.9747ZM10.5216 2.84515C10.5988 2.81319 10.716 2.78372 10.9567 2.76729C11.2042 2.75041 11.5238 2.75 12 2.75C12.4762 2.75 12.7958 2.75041 13.0432 2.76729C13.284 2.78372 13.4012 2.81319 13.4783 2.84515C13.7846 2.97202 14.028 3.21536 14.1548 3.52165C14.1949 3.61826 14.228 3.76887 14.2414 4.12597C14.271 4.91835 14.68 5.68129 15.4061 6.10048C16.1321 6.51968 16.9974 6.4924 17.6984 6.12188C18.0143 5.9549 18.1614 5.90832 18.265 5.89467C18.5937 5.8514 18.9261 5.94047 19.1891 6.14228C19.2554 6.19312 19.3395 6.27989 19.4741 6.48016C19.6125 6.68603 19.7726 6.9626 20.0107 7.375C20.2488 7.78741 20.4083 8.06438 20.5174 8.28713C20.6235 8.50382 20.6566 8.62007 20.6675 8.70287C20.7108 9.03155 20.6217 9.36397 20.4199 9.62698C20.3562 9.70995 20.2424 9.81399 19.9397 10.0041C19.2684 10.426 18.8122 11.1616 18.8121 11.9999C18.8121 12.8383 19.2683 13.574 19.9397 13.9959C20.2423 14.186 20.3561 14.29 20.4198 14.373C20.6216 14.636 20.7107 14.9684 20.6674 15.2971C20.6565 15.3799 20.6234 15.4961 20.5173 15.7128C20.4082 15.9355 20.2487 16.2125 20.0106 16.6249C19.7725 17.0373 19.6124 17.3139 19.474 17.5198C19.3394 17.72 19.2553 17.8068 19.189 17.8576C18.926 18.0595 18.5936 18.1485 18.2649 18.1053C18.1613 18.0916 18.0142 18.045 17.6983 17.8781C16.9973 17.5075 16.132 17.4803 15.4059 17.8995C14.68 18.3187 14.271 19.0816 14.2414 19.874C14.228 20.2311 14.1949 20.3817 14.1548 20.4784C14.028 20.7846 13.7846 21.028 13.4783 21.1549C13.4012 21.1868 13.284 21.2163 13.0432 21.2327C12.7958 21.2496 12.4762 21.25 12 21.25C11.5238 21.25 11.2042 21.2496 10.9567 21.2327C10.716 21.2163 10.5988 21.1868 10.5216 21.1549C10.2154 21.028 9.97201 20.7846 9.84514 20.4784C9.80512 20.3817 9.77195 20.2311 9.75859 19.874C9.72896 19.0817 9.31997 18.3187 8.5939 17.8995C7.86784 17.4803 7.00262 17.5076 6.30158 17.8781C5.98565 18.0451 5.83863 18.0917 5.73495 18.1053C5.40626 18.1486 5.07385 18.0595 4.81084 17.8577C4.74458 17.8069 4.66045 17.7201 4.52586 17.5198C4.38751 17.314 4.22736 17.0374 3.98926 16.625C3.75115 16.2126 3.59171 15.9356 3.4826 15.7129C3.37646 15.4962 3.34338 15.3799 3.33248 15.2971C3.28921 14.9684 3.37828 14.636 3.5801 14.373C3.64376 14.2901 3.75761 14.186 4.0602 13.9959C4.73158 13.5741 5.18782 12.8384 5.18786 12.0001C5.18791 11.1616 4.73165 10.4259 4.06021 10.004C3.75769 9.81389 3.64385 9.70987 3.58019 9.62691C3.37838 9.3639 3.28931 9.03149 3.33258 8.7028C3.34348 8.62001 3.37656 8.50375 3.4827 8.28707C3.59181 8.06431 3.75125 7.78734 3.98935 7.37493C4.22746 6.96253 4.3876 6.68596 4.52596 6.48009C4.66055 6.27983 4.74468 6.19305 4.81093 6.14222C5.07395 5.9404 5.40636 5.85133 5.73504 5.8946C5.83873 5.90825 5.98576 5.95483 6.30173 6.12184C7.00273 6.49235 7.86791 6.51962 8.59394 6.10045C9.31998 5.68128 9.72896 4.91837 9.75859 4.12602C9.77195 3.76889 9.80512 3.61827 9.84514 3.52165C9.97201 3.21536 10.2154 2.97202 10.5216 2.84515Z"
                                    fill="#F5F5F5" />
                            </svg>
                        </button>
                    </div>
                    <div>
                        <div class="flex items-center justify-between text-white">
                            <p class="text-lg">Email</p>
                            <h5 class="text-base ml-auto overflow-hidden pl-4">
                                {{ $client->email ? $client->email : 'N/A' }}
                            </h5>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-white">
                            <p class="text-lg">Phone</p>
                            <h5 class="text-base ml-auto">{{ $client->phone ? $client->phone : 'N/A' }}</h5>
                        </div>
                        <div class="mt-2 flex gap-2 items-center justify-between text-white">
                            <p class="text-lg">Address</p>
                            <h5 class="ml-5 text-base whitespace-nowrap overflow-x-auto scroll-auto">
                                {{ $client->address ? $client->address : 'N/A' }}
                            </h5>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-white">
                            <p class="text-lg">Agent</p>
                            <h5 class="text-base ml-auto">{{ $client->agent->name }}</h5>
                        </div>
                    </div>
                    <div class="invoice-status flex gap-2 mt-2">
                        <x-paid>{{ $paid }} KWD</x-paid>
                        <x-unpaid>{{ $unpaid }} KWD</x-unpaid>
                    </div>
                </div>

            </div>
        </div>


        <div class="mt-5 panel">
            <div class="mb-5 flex justify-between">
                <h5 class="text-lg font-semibold dark:text-white-light">
                    Invoices List
                </h5>
                <!-- add an icon here -->
            </div>
            <!-- tasks Section -->
            <div class="mt-5 overflow-x-auto">
                <div class="max-h-96 overflow-y-auto custom-scrollbar">
                    <!-- Client Orders Section -->
                    <table class="table-auto w-full text-center border-collapse">
                        <thead>
                            <tr>
                                <th>Invoice Number</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Agent</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($invoices->count() > 0)
                            @foreach ($invoices as $invoice)
                            <tr>
                                <td> 
                                    <a href="{{ url('/invoice/' . $invoice->invoice_number) }}" class="text-blue-500 hover:underline" target="_blank">{{ $invoice->invoice_number }}</a>
                                </td>
                                <td> {{ $invoice->amount }} </td>
                                <td>
                                    @if (strtolower($invoice->status) == 'paid')
                                    <x-paid>
                                        {{ $invoice->status }}
                                    </x-paid>
                                    @else
                                    <x-unpaid>
                                        {{ $invoice->status }}
                                    </x-unpaid>
                                    @endif
                                </td>
                                <td>
                                    {{ $invoice->agent->name }}
                                </td>
                                <td x-data='{ invoiceModal : false }'>
                                    <button @click="invoiceModal = true"
                                        class="text-blue-500 hover:text-blue-700">
                                        <svg width="24" height="24" viewBox="0 0 24 24"
                                            fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd"
                                                d="M11.9426 1.25L13.5 1.25C13.9142 1.25 14.25 1.58579 14.25 2C14.25 2.41421 13.9142 2.75 13.5 2.75H12C9.62177 2.75 7.91356 2.75159 6.61358 2.92637C5.33517 3.09825 4.56445 3.42514 3.9948 3.9948C3.42514 4.56445 3.09825 5.33517 2.92637 6.61358C2.75159 7.91356 2.75 9.62177 2.75 12C2.75 14.3782 2.75159 16.0864 2.92637 17.3864C3.09825 18.6648 3.42514 19.4355 3.9948 20.0052C4.56445 20.5749 5.33517 20.9018 6.61358 21.0736C7.91356 21.2484 9.62177 21.25 12 21.25C14.3782 21.25 16.0864 21.2484 17.3864 21.0736C18.6648 20.9018 19.4355 20.5749 20.0052 20.0052C20.5749 19.4355 20.9018 18.6648 21.0736 17.3864C21.2484 16.0864 21.25 14.3782 21.25 12V10.5C21.25 10.0858 21.5858 9.75 22 9.75C22.4142 9.75 22.75 10.0858 22.75 10.5V12.0574C22.75 14.3658 22.75 16.1748 22.5603 17.5863C22.366 19.031 21.9607 20.1711 21.0659 21.0659C20.1711 21.9607 19.031 22.366 17.5863 22.5603C16.1748 22.75 14.3658 22.75 12.0574 22.75H11.9426C9.63423 22.75 7.82519 22.75 6.41371 22.5603C4.96897 22.366 3.82895 21.9607 2.93414 21.0659C2.03933 20.1711 1.63399 19.031 1.43975 17.5863C1.24998 16.1748 1.24999 14.3658 1.25 12.0574V11.9426C1.24999 9.63423 1.24998 7.82519 1.43975 6.41371C1.63399 4.96897 2.03933 3.82895 2.93414 2.93414C3.82895 2.03933 4.96897 1.63399 6.41371 1.43975C7.82519 1.24998 9.63423 1.24999 11.9426 1.25ZM16.7705 2.27592C18.1384 0.908029 20.3562 0.908029 21.7241 2.27592C23.092 3.6438 23.092 5.86158 21.7241 7.22947L15.076 13.8776C14.7047 14.2489 14.4721 14.4815 14.2126 14.684C13.9069 14.9224 13.5761 15.1268 13.2261 15.2936C12.929 15.4352 12.6169 15.5392 12.1188 15.7052L9.21426 16.6734C8.67801 16.8521 8.0868 16.7126 7.68711 16.3129C7.28742 15.9132 7.14785 15.322 7.3266 14.7857L8.29477 11.8812C8.46079 11.3831 8.56479 11.071 8.7064 10.7739C8.87319 10.4239 9.07761 10.0931 9.31605 9.78742C9.51849 9.52787 9.7511 9.29529 10.1224 8.924L16.7705 2.27592ZM20.6634 3.33658C19.8813 2.55448 18.6133 2.55448 17.8312 3.33658L17.4546 3.7132C17.4773 3.80906 17.509 3.92327 17.5532 4.05066C17.6965 4.46372 17.9677 5.00771 18.48 5.51999C18.9923 6.03227 19.5363 6.30346 19.9493 6.44677C20.0767 6.49097 20.1909 6.52273 20.2868 6.54543L20.6634 6.16881C21.4455 5.38671 21.4455 4.11867 20.6634 3.33658ZM19.1051 7.72709C18.5892 7.50519 17.9882 7.14946 17.4193 6.58065C16.8505 6.01185 16.4948 5.41082 16.2729 4.89486L11.2175 9.95026C10.801 10.3668 10.6376 10.532 10.4988 10.7099C10.3274 10.9297 10.1804 11.1676 10.0605 11.4192C9.96337 11.623 9.88868 11.8429 9.7024 12.4017L9.27051 13.6974L10.3026 14.7295L11.5983 14.2976C12.1571 14.1113 12.377 14.0366 12.5808 13.9395C12.8324 13.8196 13.0703 13.6726 13.2901 13.5012C13.468 13.3624 13.6332 13.199 14.0497 12.7825L19.1051 7.72709Z"
                                                fill="#1C274C" />
                                        </svg>
                                    </button>
                                    <div x-cloak x-show="invoiceModal"
                                        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-10">
                                        <div @click.away="invoiceModal = false"
                                            class="bg-white rounded-md p-4 w-96 h-auto">
                                            <div
                                                class="header bg-gray-400 text-white rounded-sm shadow-md my-2 p-2">
                                                <h3 class="">Invoice Details</h3>
                                            </div>
                                            <form action="{{ route('invoice.update', $invoice->id) }}"
                                                method="POST" class="grid gap-4">
                                                @method('PUT')
                                                @csrf
                                                <input type="hidden" name="id"
                                                    value="{{ $invoice->id }}">
                                                <input type="text" name="amount"
                                                    value="{{ $invoice->amount }}"
                                                    class="border border-gray-200 dark:border-gray-600 p-2 rounded-md">
                                                <select name="agent_id"
                                                    class="border border-gray-200 dark:border-gray-600 p-2 rounded-md">
                                                    @foreach ($agents as $agent)
                                                    <option value="{{ $agent->id }}"
                                                        {{ $invoice->agent_id == $agent->id ? 'selected' : '' }}>
                                                        {{ $agent->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                                <select name="status" name="status"
                                                    class="border border-gray-200 dark:border-gray-600 p-2 rounded-md">
                                                    <option value="paid"
                                                        {{ $invoice->status == 'paid' ? 'selected' : '' }}>Paid
                                                    </option>
                                                    <option value="partial"
                                                        {{ $invoice->status == 'partial' ? 'selected' : '' }}>
                                                        Partial</option>
                                                    <option value="unpaid"
                                                        {{ $invoice->status == 'unpaid' ? 'selected' : '' }}>
                                                        Unpaid</option>
                                                </select>
                                                <button type="submit"
                                                    class="p-2 rounded-md bg-black text-white">Update
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                </div>
                </td>
                </tr>
                @endforeach
                @else
                <tr>
                    <td colspan="4" class="text-gray-500 dark:text-gray-400">No invoices found for this client.
                    </td>
                </tr>
                @endif
                </tbody>
                </table>

            </div>
        </div>
    </div>
    <div class="mt-5 panel">
        <div class="mb-5 flex justify-between">
            <h5 class="text-lg font-semibold dark:text-white-light">
                Payment Link
            </h5>
            <a href="{{ route('payment.link.create') }}" class="bg-blue-600 hover:bg-blue-700 rounded-full shadow-md text-white text-sm px-3 py-2">
                Create Payment Link
            </a>
        </div>
        <div class="mt-5 overflow-x-auto">
            <div class="max-h-96 overflow-y-auto custom-scrollbar">
                <table class="table-auto w-full text-center border-collapse">
                    <thead>
                        <tr>
                            <th>Invoice Link</th>
                            <th>Agent</th>
                            <th>Payment Type</th>
                            <th>Notes</th>
                            <th>Amount</th>
                            <th>Created At</th>
                            <th>Created By</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($payments->count() > 0)
                        @foreach ($payments as $payment)
                            @php
                                $paymentUrl = route('payment.link.show', [
                                    'voucherNumber' => $payment->voucher_number,
                                ]);
                            @endphp
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <a href="{{ $paymentUrl }}" target="_blank"
                                        class="text-blue-500 hover:underline text-sm font-semibold">{{ $payment->voucher_number }}</a>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm font-semibold">
                                    {{ $payment->agent ? $payment->agent->name : 'N/A' }}
                                </td>
                                <td class="px-3 py-2 break-words text-sm">
                                    @php
                                        $gateway = $payment->payment_gateway ?? 'N/A';
                                        $method = $payment->paymentMethod->english_name ?? null;
                                    @endphp
                                    {{ $gateway === 'MyFatoorah' && $method ? "$gateway - $method" : $gateway }}
                                </td>
                                <td class="px-3 py-2 text-sm break-words max-w-[350px]">
                                    {{ $payment->notes ?? 'No Notes' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm font-semibold">
                                    {{ $payment->amount }}
                                </td>
                                @if (auth()->user()->role->name === 'admin' || auth()->user()->role->name === 'company')
                                    <td class="px-3 py-2 whitespace-nowrap text-sm">
                                        {{ $payment->created_at->format('d-m-Y H:i:s') }}
                                    </td>
                                @else
                                    <td class="px-3 py-2 text-sm break-words max-w-[200px]">
                                        {{ $payment->created_at->format('D d M Y') }}
                                    </td>
                                @endif
                                <td class="px-3 py-2 whitespace-nowrap text-sm">
                                    {{ $payment->createdBy ? $payment->createdBy->name : 'N/A' }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm font-semibold">
                                    @php
                                        $payment_reference = $payment->payment_reference
                                            ? ($payment->invoice_ref
                                                ? $payment->payment_reference . '/' . $payment->invoice_ref
                                                : $payment->payment_reference)
                                            : 'N/A';
                                        $isTrimmed = strlen($payment_reference) > 15;
                                        $trimmedValue = \Illuminate\Support\Str::limit($payment_reference, 15);
                                    @endphp

                                    @if ($isTrimmed)
                                        <span x-data="{ showFullData: false }">
                                            <span x-show="!showFullData" @click="showFullData = !showFullData"
                                                class="cursor-pointer hover:text-purple-700"
                                                data-tooltip-left="Click to expand">
                                                {{ $trimmedValue }}
                                            </span>

                                            <span x-show="showFullData" @click="showFullData = !showFullData"
                                                class="cursor-pointer hover:text-purple-500">
                                                {{ $payment_reference }}
                                            </span>
                                        </span>
                                    @else
                                        <span>{{ $payment_reference }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-sm">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-600',
                                            'completed' => 'bg-green-100 text-green-800 border-green-600',
                                            'failed' => 'bg-red-100 text-red-800 border-red-600',
                                            'cancelled' => 'bg-gray-100 text-gray-600 border-gray-600',
                                        ];
                                        $status = strtolower($payment->status);
                                        $colorClass =
                                            $statusColors[$status] ??
                                            'bg-gray-100 text-gray-800 border-gray-600';
                                    @endphp
                                    <span
                                        class="inline-block px-3 py-1.5 rounded-full font-semibold text-center {{ $colorClass }} border-2 transition-all duration-200 ease-in-out transform hover:scale-105 hover:shadow-lg">
                                        {{ ucfirst($payment->status) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap relative text-sm">
                                    <div x-data="{ 
                                        open: false, 
                                        editPaymentLink: false,
                                        dropdownPosition: 'bottom',
                                        checkPosition() {
                                            this.$nextTick(() => {
                                                if (this.open) {
                                                    const button = this.$refs.dropdownButton;
                                                    const dropdown = this.$refs.dropdownMenu;
                                                    const buttonRect = button.getBoundingClientRect();
                                                    const dropdownHeight = dropdown.offsetHeight;
                                                    const viewportHeight = window.innerHeight;
                                                    const spaceBelow = viewportHeight - buttonRect.bottom;
                                                    const spaceAbove = buttonRect.top;
                                                    
                                                    if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
                                                        this.dropdownPosition = 'top';
                                                    } else {
                                                        this.dropdownPosition = 'bottom';
                                                    }
                                                }
                                            });
                                        },
                                        toggleDropdown() {
                                            this.open = !this.open;
                                            if (this.open) {
                                                this.checkPosition();
                                            }
                                        }
                                    }" class="relative inline-block text-left">
                                        <button 
                                            x-ref="dropdownButton"
                                            @click="toggleDropdown()" 
                                            @click.outside="open = false" 
                                            class="p-1 rounded hover:bg-gray-100">
                                            <svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 13a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM10 20a1.5 1.5 0 110-3 1.5 1.5 0 010 3z" />
                                            </svg>
                                        </button>
                                        <div 
                                            x-ref="dropdownMenu"
                                            x-cloak 
                                            x-show="open" 
                                            x-transition 
                                            :class="{
                                                'absolute right-[-20px] mt-2': dropdownPosition === 'bottom',
                                                'absolute right-[-20px] bottom-full mb-2': dropdownPosition === 'top'
                                            }"
                                            class="w-46 bg-white border border-gray-200 rounded-md shadow-lg z-50">
                                            <form action="{{ route('resayil.share-payment-link') }}" method="POST" class="block">
                                                @csrf
                                                <input type="hidden" name="client_id" value="{{ $payment->client_id }}">
                                                <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                                <input type="hidden" name="voucher_number" value="{{ $payment->voucher_number }}">
                                                <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <svg class="h-5 w-5 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                                </svg>
                                                Send Link
                                                </button>
                                            </form>
                                            <button onclick="copyToClipboard('{{ $paymentUrl }}')" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <svg class="h-5 w-5 mr-2 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 16h8M8 12h8m-6 8h6a2 2 0 002-2V7a2 2 0 00-2-2H9m-2 0H7a2 2 0 00-2 2v12a2 2 0 002 2h2V5z" />
                                                </svg>
                                                Copy Link
                                            </button>
                                            <a href="{{ $paymentUrl }}" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                <svg class="h-4 w-4 mr-1 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                                View Invoice
                                            </a>

                                            @if ($payment->status === 'pending')
                                            <div class="border-t border-gray-200 my-1"></div>
                                            <button @click="editPaymentLink = true; open = false" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-blue-600 hover:bg-blue-50">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path d="M12 20h9M15 3l6 6-9 9H6v-6l9-9z"/>
                                                </svg>
                                                Edit    
                                            </button>
                                            <form action="{{ route('payment.link.delete', $payment->id) }}" method="POST" class="block">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                                Delete
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                        <div x-cloak x-transition x-show="editPaymentLink" class="fixed inset-0 z-10 bg-gray-500 bg-opacity-50 flex items-center justify-center">
                                            <div
                                                class="bg-white p-6 rounded shadow-lg w-full max-w-md relative">
                                                <div class="flex items-center justify-between mb-6">
                                                    <div>
                                                        <h2 class="text-xl font-bold text-gray-800">Edit
                                                            Payment
                                                            Link Details</h2>
                                                        <p class="text-gray-600 italic text-xs mt-1">Please
                                                            update
                                                            the payment link details to ensure accurate
                                                            information
                                                        </p>
                                                    </div>
                                                    <button @click="editPaymentLink = false"
                                                        class="absolute top-2 right-2 p-2 text-gray-400 hover:text-red-500 text-2xl">
                                                        &times;
                                                    </button>
                                                </div>
                                                <form
                                                    action="{{ route('payment.link.update', $payment->id) }}"
                                                    method="POST">
                                                    @csrf
                                                    @method('PUT')
                                                    @unlessrole('agent')
                                                        @php
                                                            $selectedAgent = \App\Models\Agent::find(
                                                                $payment->agent_id,
                                                            );
                                                            $agentPlaceholder = $selectedAgent
                                                                ? $selectedAgent->name
                                                                : 'Select an Agent';
                                                        @endphp

                                                        <div class="mb-4">
                                                            <x-searchable-dropdown name="agent_id"
                                                                :items="$agents->map(
                                                                    fn($a) => [
                                                                        'id' => $a->id,
                                                                        'name' => $a->name,
                                                                    ],
                                                                )" :placeholder="$agentPlaceholder"
                                                                :selectedName="$selectedAgent
                                                                    ? $selectedAgent->name
                                                                    : null" label="Agent" />
                                                        </div>
                                                    @else
                                                        <div class="mb-4">
                                                            <input type="hidden" name="agent_id"
                                                                value="{{ auth()->user()->agent->id }}">
                                                        </div>
                                                    @endunlessrole

                                                    @php
                                                        $selectedClient = \App\Models\Client::find(
                                                            $payment->client_id,
                                                        );
                                                        $clientPlaceholder = $selectedClient
                                                            ? $selectedClient->first_name
                                                            : 'Select a Client';
                                                    @endphp
                                                    <div class="mb-4">
                                                        <x-searchable-dropdown name="client_id"
                                                            :items="$clients->map(
                                                                fn($c) => [
                                                                    'id' => $c->id,
                                                                    'name' => $c->first_name . ' ' . $c->middle_name . ' ' . $c->last_name . ' - ' . $c->phone
                                                                ],
                                                            )" :placeholder="$clientPlaceholder"
                                                            :selectedName="$selectedClient
                                                                ? $selectedClient->first_name
                                                                : null" label="Client" />

                                                        <input type="hidden" name="client_id_fallback"
                                                            value="{{ $selectedClient ? $selectedClient->id : '' }}">
                                                    </div>

                                                    <label for="phone_{{ $payment->client_id }}"
                                                        class="block text-sm font-medium text-gray-700">Phone
                                                        Number</label>
                                                    @php
                                                        $client = \App\Models\Client::find(
                                                            $payment->client_id,
                                                        );
                                                        $placeholder = $client
                                                            ? $client->country_code
                                                            : 'Select Dial Code';
                                                    @endphp
                                                    <div class="flex gap-4 mb-4">
                                                        <div class="w-2/5">
                                                            <x-searchable-dropdown name="dial_code"
                                                                :items="\App\Models\Country::all()->map(
                                                                    fn($country) => [
                                                                        'id' => $country->dialing_code,
                                                                        'name' =>
                                                                            $country->dialing_code .
                                                                            ' ' .
                                                                            $country->name,
                                                                    ],
                                                                )" :placeholder="$placeholder"
                                                                :selectedName="$client
                                                                    ? $client->country_code
                                                                    : null" :showAllOnOpen="true" />

                                                            <input type="hidden"
                                                                name="dial_code_fallback"
                                                                value="{{ $client ? $client->country_code : '' }}">
                                                        </div>

                                                        <div class="w-3/5">
                                                            <input type="text" name="phone"
                                                                id="phone_{{ $payment->client_id }}"
                                                                value="{{ $client ? $client->phone : '' }}"
                                                                placeholder="Phone Number"
                                                                class="form-input w-full border rounded px-3 py-2"
                                                                required />
                                                        </div>
                                                    </div>

                                                    <div class="mb-4" x-data="{ selectedGateway: '{{ $payment->payment_gateway ?? '' }}', selectedMethod: '{{ $payment->paymentMethod ? $payment->paymentMethod->id : '' }}' }">
                                                        <div
                                                            :class="selectedGateway === 'MyFatoorah' ?
                                                                'grid grid-cols-1 md:grid-cols-2 gap-6 items-start' :
                                                                'block'">
                                                            <div>
                                                                <label for="payment-gateway"
                                                                    class="block text-sm font-medium text-gray-700">Payment
                                                                    Gateway</label>
                                                                <select name="payment_gateway"
                                                                    id="payment_gateway"
                                                                    class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                    x-model="selectedGateway">
                                                                    <option value="" disabled>Select
                                                                        Payment
                                                                        Gateway</option>
                                                                    @foreach ($paymentGateways as $gateway)
                                                                        <option
                                                                            value="{{ $gateway->name }}"
                                                                            @if ($payment->payment_gateway === $gateway->name) selected @endif>
                                                                            {{ $gateway->name }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>

                                                            <template
                                                                x-if="selectedGateway === 'MyFatoorah'">
                                                                <div>
                                                                    <label for="payment-method"
                                                                        class="block text-sm font-medium text-gray-700">Payment
                                                                        Method</label>
                                                                    <select name="payment_method_id"
                                                                        id="payment_method_id"
                                                                        class="p-2 mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                                        x-model="selectedMethod">
                                                                        <option value="" disabled>
                                                                            Select
                                                                            Method</option>
                                                                        @foreach ($paymentMethods as $method)
                                                                            <option
                                                                                value="{{ $method->id }}"
                                                                                @if ($payment->payment_method_id === $method->id) selected @endif>
                                                                                {{ $method->english_name }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                    <div class="mb-4">
                                                        <label for="amount"
                                                            class="block text-sm font-medium text-gray-700">Amount</label>
                                                        <input type="text" name="amount"
                                                            id="amount" value="{{ $payment->amount }}"
                                                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                                    </div>
                                                    <div class="flex justify-between space-x-4">
                                                        <button type="button"
                                                            @click="editPaymentLink = false"
                                                            class="rounded-full shadow-md border border-gray-200 hover:bg-gray-400 px-4 py-2">Cancel</button>
                                                        <button type="submit"
                                                            class="rounded-full shadow-md border border-blue-200 bg-blue-600 text-white hover:bg-blue-700 px-4 py-2">Update</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @else
                        <tr class="border-t">
                            <td colspan="10" class="py-4 text-gray-500">No payments found for this client.</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- edit Agent details modal -->
    <div id="editClientModal"
        class="fixed z-10 inset-0 flex items-center justify-center bg-gray-900 bg-opacity-50 backdrop-blur-sm hidden">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 relative max-h-[90vh] overflow-y-auto">

            <!-- Close Button (Top Right) -->
            <button onclick="closeClientModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            <!-- Modal Title -->
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4 text-center">Edit Client Details
            </h2>

            <div class="body p-4">
                <form action="{{ route('clients.update', $client->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-4">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" id="first_name" value="{{ $client->first_name }}"
                                class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full"
                                placeholder="First Name" required>
                        </div>
                        <div>
                            <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                            <input type="text" name="middle_name" id="middle_name" value="{{ $client->middle_name }}"
                                class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full"
                                placeholder="Middle Name">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" id="last_name" value="{{ $client->last_name }}"
                                class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full"
                                placeholder="Last Name">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" id="email" value="{{ $client->email }}"
                                class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full"
                                placeholder="Client Email">
                        </div>
                        <div>
                            <label for="country_code" class="block text-sm font-medium text-gray-700 mb-1">Country Code</label>
                            <select name="country_code" id="country_code"
                                class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full">
                                @foreach ($countries as $country)
                                <option value="{{ $country->dialing_code }}"
                                    {{ $client->country_code == $country->dialing_code ? 'selected' : '' }}>
                                    {{ $country->name }} ({{ $country->dialing_code }})
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ $client->phone }}"
                                class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full"
                                placeholder="Client Phone">
                        </div>
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <input type="text" name="address" id="address" value="{{ $client->address }}"
                                class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full"
                                placeholder="Client Address">
                        </div>
                        <div>
                            <label for="agent_id" class="block text-sm font-medium text-gray-700 mb-1">Agent</label>
                            <select name="agent_id" id="agent_id" class="border border-gray-200 dark:border-gray-600 p-2 rounded-md w-full">
                                @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}"
                                    {{ $client->agent_id == $agent->id ? 'selected' : '' }}>
                                    {{ $agent->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="p-2 rounded-md bg-black text-white mt-2">Update</button>
                    </div>
                </form>
            </div>


        </div>
    </div>
    <!-- ./edit agent details modal -->

    </div> <!-- ./p-3 -->

    <!-- Clients Modal -->
    <div id="clientModal"
        class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden ">
        <div class="bg-white border rounded-lg shadow-lg  w-3/4 md:w-1/2 mb-10">
            <!-- Modal Header -->
            <div class="border rounded-t-lg mb-5 flex items-center justify-between bg-[#fbfbfb] px-5 py-3">
                <h5 class="text-lg font-bold">Client Management</h5>
                <button type="button" class="text-white-dark hover:text-dark" id="closeClientModalButton">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"
                        stroke-linejoin="round" class="h-6 w-6">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <!-- ./Modal Header -->

            <!-- Tabs -->
            <div class="border-b flex justify-center">
                <button class="tab-button px-4 py-2 text-blue-500 border-b-2 border-blue-500"
                    id="selectTabButton">Select Client</button>
            </div>
            <!-- ./Tabs -->

            <!-- Tab Content -->
            <div id="selectTab" class="p-6">
                <!-- Search Box -->
                <div class="relative mb-4">
                    <input type="text" placeholder="Search Client..."
                        class="form-input h-11 rounded-full bg-white shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] placeholder:tracking-wider"
                        id="clientSearchInput">
                </div>
                <!-- ./Search Box -->

                <!-- List of Clients -->
                <ul id="clientList"
                    class="shadow-[0_0_4px_2px_rgb(31_45_61_/_10%)] border rounded-lg mb-4 max-h-60 overflow-y-auto custom-scrollbar">
                    <!-- Dynamic list items go here -->
                </ul>
                <!-- ./List of Clients -->
            </div>
        </div>
    </div>
    <script>
        function EditClientDetails() {
            editClientModal.classList.remove('hidden');
        }

        function closeClientModal() {
            document.getElementById('editClientModal').classList.add('hidden');
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                const toast = document.createElement('div');
                toast.textContent = 'Link copied to clipboard!';
                toast.className =
                    'alert-success fixed mt-5 top-1 right-4 bg-green-500 text-white p-4 rounded shadow-lg';
                toast.innerHTML = `
            <span class="mr-4">${toast.textContent}</span>
            <button type="button" class="text-white font-bold" aria-label="Close" onclick="this.parentElement.remove()">
                &times;
            </button>
        `;
                document.body.appendChild(toast);
            }).catch(function(err) {
                console.error('Copy failed:', err);
                alert('Could not copy. Please try again.');
            });
        }
    </script>

</x-app-layout>