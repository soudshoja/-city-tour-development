<!-- filepath: resources/views/coa/partials/asset-item.blade.php -->
<li x-data="{ open: false }" class="relative w-full flex flex-col">
    <a href="javascript:;"
        class="flex items-center px-4 py-2 w-full hover:text-[#508D4E] transition-all"
        :class="{'border-l-4 border-[#508D4E] text-[#000000]': open}"
        @click="open = !open">
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