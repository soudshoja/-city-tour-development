<!-- filepath: resources/views/coa/partials/asset-item.blade.php -->
<li x-data="{ open: false , showAddCategoryForm: false}"
    class="relative w-full flex flex-col"
    :class="{ 'pointer-events-none: showAddCategoryForm }">
    <a href="javascript:;"
        class="flex items-center px-4 py-2 w-full hover:text-[#508D4E] transition-all"
        @click="if (!showAddCategoryForm) open = !open">
        <span class="flex-1">{{ $account->name }}</span>
        <div class="flex items-center gap-2">
            <svg x-show="!open" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <svg x-show="open" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 4L10 20L4 14.5" stroke="#00ab55" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                <path opacity="0.5" d="M14 20L14 4L20 9.5" stroke="#1C274C" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            @if($account->is_group)
            <button @click.stop="showAddCategoryForm = !showAddCategoryForm"
                class="text-green-600 hover:text-green-800">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" />
                </svg>
            </button>
            @else
            <div class="w-6"></div>
            @endif
        </div>
        <div x-show="showAddCategoryForm"
            x-cloak
            @keydown.escape.window="showAddCategoryForm = false"
            class="fixed inset-0 flex items-center justify-center z-50 bg-gray-800 bg-opacity-75 transition-opacity cursor-default">
            <div
                @click.away="showAddCategoryForm = false"
                class="bg-white rounded-xl shadow-lg w-full max-w-lg mx-4 p-6 relative">
                <button @click="showAddCategoryForm = false" class="absolute top-3 right-3 text-2xl text-gray-700 hover:text-black">
                    &times;
                </button>
                <h2 class="text-xl font-semibold mb-3">New Asset</h2>
                <hr class="mb-3">
                <form action="{{ route('coa.addCategory') }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Category Name<span class="text-red-500"> *</span></label>
                        <input type="text" name="name" required placeholder="Enter category name"
                            class="w-full border rounded text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium mb-1">Code<span class="text-red-500"> *</span></label>
                        <input type="text" name="code" required placeholder="Enter code"
                            class="w-full border rounded text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                    <!-- <div class="mb-4" x-data x-init="new TomSelect($refs.accountType, { closeAfterSelect: true, hideSelected: true, create: false})">
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
                    <div class="mb-4" x-data x-init="new TomSelect($refs.entity, { closeAfterSelect: true, hideSelected: true, create: false})">
                        <label class="block text-sm font-medium mb-1">
                            Entity
                        </label>
                        <select 
                            data-level = "{{ $account->level }}" 
                            data-account-id="{{ $account->id }}"
                            name="entity" x-ref="entity" class="entitySelect" placeholder="Select entity" autocomplete="off">
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
                        <button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-800">
                            Create New
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </a>

    <ul x-show="open" class="ml-6 space-y-2">
        @if($account->childAccounts && $account->childAccounts->isNotEmpty())
        @foreach ($account->childAccounts as $childAccount)
        @include('coa.partials.asset-item', ['account' => $childAccount])
        @endforeach
        @else
        <p class="text-red-400">No child accounts available.</p>
        @endif
    </ul>
</li>
<script>
</script>