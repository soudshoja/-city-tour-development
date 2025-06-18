<li x-data="{ open: false, showAddCategoryForm: false }" class="relative w-full flex flex-col cursor-pointer"
    :class="{ 'pointer-events-none: showAddCategoryForm }">
    <div class="flex gap-2 border-b border-b-[#E5E7EB] dark:border-b-[#374151]">
        <div class="flex justify-between py-2 w-full hover:text-[#508D4E] transition-all  dark:hover:text-[#00ab55] dark:text-white"
            @click="if (!showAddCategoryForm) open = !open">
            <span class="px-2 flex-shrink-0">{{ $account->name }}</span>
            <div class="flex justify-start w-120">
                <div
                    class="p-2 min-w-8 w-fit h-fit text-xs text-center rounded-full font-semibold text-{{ $color }}-600 bg-{{ $color }}-100">
                    {{ $account->code }}
                </div>
            </div>
            <div
                class="p-2  min-w-8 w-fit h-fit text-xs text-center rounded-full font-semibold text-{{ $color }}-600 bg-{{ $color }}-100">
                {{ $account->balance }}
            </div>
            <div class="px-2 flex items-center gap-2">
                @if($account->name == 'Amadeus' && $account->root->name == 'Liabilities' && $account->journalEntries->count() == 0)
                <div x-data='{ delegateBalanceAmadeus : false }' class="flex items-center" data-tooltip-left="You have child accounts for Amadeus, you have to delegate the balance to the company that issued tasks">
                    <button @click="delegateBalanceAmadeus = !delegateBalanceAmadeus"
                        class="text-red-600 hover:text-red-800 animate-pulse">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="stroke-red-500">
                            <path d="M9.5 14C11.1569 14 12.5 15.3431 12.5 17C12.5 18.6568 11.1569 20 9.5 20C7.84315 20 6.5 18.6568 6.5 17C6.5 15.3431 7.84315 14 9.5 14Z" stroke-width="1.5" />
                            <path d="M14.5 3.99998C12.8431 3.99998 11.5 5.34312 11.5 6.99998C11.5 8.65683 12.8431 9.99998 14.5 9.99998C16.1569 9.99998 17.5 8.65683 17.5 6.99998C17.5 5.34312 16.1569 3.99998 14.5 3.99998Z" stroke-width="1.5" />
                            <path d="M15 16.9585L22 16.9585" stroke-width="1.5" stroke-linecap="round" />
                            <path d="M9 6.9585L2 6.9585" stroke-width="1.5" stroke-linecap="round" />
                            <path d="M2 16.9585L4 16.9585" stroke-width="1.5" stroke-linecap="round" />
                            <path d="M22 6.9585L20 6.9585" stroke-width="1.5" stroke-linecap="round" />
                        </svg>
                    </button>
                    <div
                        x-cloak
                        x-show="delegateBalanceAmadeus" class="fixed inset-0 flex items-center justify-center z-50 bg-gray-800 bg-opacity-75 transition-opacity cursor-default text-black dark:text-white">
                        <div @click.away="delegateBalanceAmadeus = false"
                            class="bg-white rounded-xl shadow-lg w-full max-w-lg mx-4 p-6 relative">
                            <button @click="delegateBalanceAmadeus = false"
                                class="absolute top-3 right-3 text-2xl text-gray-700 hover:text-black">
                                &times;
                            </button>
                            <h2 class="text-xl font-semibold mb-3">Delegate Balance</h2>
                            <hr class="mb-3">
                            <form action="{{ route('coa.delegate-price') }}" method="POST">
                                @csrf
                                <p class="text-lg mb-4">
                                    Delegating the balance of Amadeus account will transfer the balance to the company that issued tasks
                                </p>
                                <p class="text-md mb-2 text-yellow-600">
                                    Please enter the code for the new account that will be created
                                </p>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium mb-1">Code<span
                                            class="text-red-500">*</span></label>
                                    <input type="hidden" name="account_id" value="{{ $account->id }}">
                                    <input type="number" name="code" required placeholder="Enter new code" min="{{ $account->code + 1 }}"
                                        class="w-full border rounded text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
                                </div>
                                <div class="text-right">
                                    <button type="submit"
                                        class="bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-800">
                                        Delegate
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif
                <svg x-show="!open" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                        stroke-linejoin="round" />
                    <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <svg x-show="open" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round"
                        stroke-linejoin="round" />
                    <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                @if ($account->is_group)
                <button @click.stop="showAddCategoryForm = !showAddCategoryForm"
                    class="text-green-600 hover:text-green-800">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </button>
                @else
                <div class="w-6"></div>
                @endif
            </div>
            <div x-show="showAddCategoryForm" x-cloak @keydown.escape.window="showAddCategoryForm = false"
                class="fixed inset-0 flex items-center justify-center z-50 bg-gray-800 bg-opacity-75 transition-opacity cursor-default">
                <div @click.away="showAddCategoryForm = false"
                    class="bg-white rounded-xl shadow-lg w-full max-w-lg mx-4 p-6 relative">
                    <button @click="showAddCategoryForm = false"
                        class="absolute top-3 right-3 text-2xl text-gray-700 hover:text-black">
                        &times;
                    </button>
                    <h2 class="text-xl font-semibold mb-3">New Account</h2>
                    <hr class="mb-3">
                    <form action="{{ route('coa.addCategory') }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Category Name<span class="text-red-500">
                                    *</span></label>
                            <input type="text" name="name" required placeholder="Enter category name"
                                class="w-full border rounded text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Code<span class="text-red-500">
                                    *</span></label>
                            <input type="text" name="code" required placeholder="Enter code"
                                class="w-full border rounded text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
                        </div>
                        <!-- <div class="mb-4" x-data x-init="new TomSelect($refs.accountType, { closeAfterSelect: true, hideSelected: true, create: false })">
                            <label class="block text-sm font-medium mb-1">
                                Account Type<span class="text-red-500"> *</span>
                            </label>
                            <select name="accountType" x-ref="accountType" id="account-type" required placeholder="Select type" autocomplete="off">
                                <option value="">Select type</option>
                                <option value="expenses">Expenses</option>
                                <option value="fixed_assets">Fixed Assets</option>
                                <option value="acc_payable">Account Payable</option>
                            </select>
                        </div> -->
                        <div class="mb-4" x-data x-init="new TomSelect($refs.entity, { closeAfterSelect: true, hideSelected: true, create: false })">
                            <label class="block text-sm font-medium mb-1">
                                Entity
                            </label>
                            <select data-level="{{ $account->level }}" data-account-id="{{ $account->id }}"
                                name="entity" x-ref="entity" class="entitySelect" placeholder="Select entity"
                                autocomplete="off">
                                <option value="">Select entity</option>
                                <option value="client">Client</option>
                                <option value="agent">Agent</option>
                                <option value="branch">Branch</option>
                            </select>
                        </div>
                        <div id="entity-container-{{ $account->id }}" class="mb-2"></div>

                        <input type="hidden" name="root_id" value="{{ $account->root_id }}">
                        <input type="hidden" name="parent_id" value="{{ $account->id }}">
                        <input type="hidden" name="level" value="{{ $account->level + 1 }}">

                        <div class="text-right">
                            <button type="submit"
                                class="bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-800">
                                Create New
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @if ($account->ledger)
        <a class="p-2 text-xs text-center text-blue-500 hover:underline" target="_blank"
            href="{{ route('journal-entries.show', $account->id) }}">
            Ledger
        </a>
        @endif
    </div>
    <ul x-show="open" class="ml-6 space-y-2">
        @if ($account->childAccounts && $account->childAccounts->isNotEmpty())
        @foreach ($account->childAccounts as $childAccount)
        @include('coa.partials.child-account', ['account' => $childAccount])
        @endforeach
        @else
        <p class="text-red-400">No child accounts available.</p>
        @endif
    </ul>
</li>
<script></script>