@props([
    'partial' => null,
    'payment' => null,
    'gatewayOnly' => false,
    'fallbackText' => null,
])

@php
    $resolvedPayment = $payment ?? $partial?->payment;
    $gateway = $partial?->payment_gateway ?? $resolvedPayment?->payment_gateway;

    $userRole = auth()->user()?->role_id;
    $canSeeGateway = $userRole !== null && in_array($userRole, [
        \App\Models\Role::ADMIN,
        \App\Models\Role::COMPANY,
        \App\Models\Role::BRANCH,
        \App\Models\Role::ACCOUNTANT,
    ], true);

    if ($fallbackText !== null) {
        $reference = $fallbackText;
    } elseif ($gatewayOnly) {
        $reference = '';
    } elseif ($gateway === 'MyFatoorah') {
        $reference = $resolvedPayment?->myfatoorahPayment?->invoice_ref
            ?? data_get($resolvedPayment?->myfatoorahPayment?->payload ?? [], 'Data.InvoiceReference')
            ?? 'N/A';
    } else {
        $reference = $resolvedPayment?->payment_reference ?? 'N/A';
    }

    if ($canSeeGateway && $gateway) {
        $output = $gatewayOnly ? $gateway : '(' . $gateway . ') ' . $reference;
    } else {
        $output = $gatewayOnly ? '—' : $reference;
    }
@endphp
{{ $output }}
