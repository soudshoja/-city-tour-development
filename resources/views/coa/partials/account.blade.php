<li class="py-2 pl-4 border-l-2 border-gray-300 hover:bg-gray-100 account-item" data-id="{{ $account->id }}" data-name="{{ $account->name }}">
    <div class="flex justify-between items-center cursor-pointer" onclick="toggleChildren(this)">
        <span class="font-medium text-gray-700">{{ $account->name }}</span>
        @if($account->level === 4)
        <div class="text-sm text-gray-600 mt-1">
            Balance: ${{ number_format($account->balance, 2) }}
        </div>
       @endif
        <span class="text-sm text-gray-500">(Level: {{ $account->level }})</span>
    </div>



    @if($account->children->isNotEmpty())
        <ul class="mt-2 hidden children">
            @foreach($account->children as $child)
                @include('coa.partials.account', ['account' => $child])
            @endforeach
        </ul>
    @endif
</li>
