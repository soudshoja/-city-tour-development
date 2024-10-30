<!-- resources/views/chart-of-accounts/partials/account-row.blade.php -->
@php
    // Generate a unique ID for each row based on the level and account ID
    $rowId = "row-" . $level . "-" . ($account['id'] ?? uniqid());
    $indent = $level * 20; // Adjust padding based on level of nesting
@endphp

<tr>
    <td class="py-2 px-4 border-b" style="padding-left: {{ $indent }}px;">
        @if(!empty($account['children']))
            <button onclick="document.getElementById('{{ $rowId }}').classList.toggle('hidden')"
                    class="mr-2 text-gray-600">
                ▶
            </button>
        @endif
        {{ $account['name'] }}
    </td>
    <td class="py-2 px-4 border-b">{{ $account['code'] ?? '' }}</td>
    <td class="py-2 px-4 border-b text-right">${{ number_format($account['actual'], 2) }}</td>
    <td class="py-2 px-4 border-b text-right">$0.00</td> <!-- Placeholder for Credit Actual -->
    <td class="py-2 px-4 border-b text-right">${{ number_format($account['actual'], 2) }}</td>
    <td class="py-2 px-4 border-b text-right">${{ number_format($account['budget'], 2) }}</td>
    <td class="py-2 px-4 border-b text-right">$0.00</td> <!-- Placeholder for Credit Budget -->
    <td class="py-2 px-4 border-b text-right">${{ number_format($account['budget'], 2) }}</td>
    <td class="py-2 px-4 border-b text-right">${{ number_format($account['variance'], 2) }}</td>
</tr>

@if(!empty($account['children']))
    <tbody id="{{ $rowId }}" class="hidden">
        @foreach($account['children'] as $child)
            @include('coa.partials.account-row', ['account' => $child, 'level' => $level + 1])
        @endforeach
    </tbody>
@endif
