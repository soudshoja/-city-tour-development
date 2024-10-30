<!-- Sub-Accounts for Expenses -->
<div id="expenses-sub-accounts" class="sub-accounts-container hidden space-y-4">
    @foreach($accounts->where('name', 'Expenses') as $account)
    @foreach($account->children as $child)
    <div class="bg-gray-100 p-4 rounded-lg text-center font-medium text-gray-700 cursor-pointer"
        onclick="showChildAccounts('{{ $child->id }}')">
        {{ $child->name }}
        @if($child->level === 4)
        <div class="text-sm text-gray-600 mt-1">
            Balance: ${{ number_format($child->balance, 2) }}
        </div>
        @endif
        <span class="text-sm text-gray-500">(Level: {{ $child->level }})</span>
    </div>
    @endforeach
    @endforeach
</div>